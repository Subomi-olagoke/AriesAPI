package chat

import (
	"encoding/json"
	"fmt"
	"sync"
	"time"
)

// Hub maintains the set of active clients and broadcasts messages to them
type Hub struct {
	// Registered clients
	Clients map[*Client]bool

	// Broadcast messages to all clients
	Broadcast chan []byte

	// Register requests from the clients
	Register chan *Client

	// Unregister requests from clients
	Unregister chan *Client

	// Message history (optional, for persisting recent messages)
	MessageHistory [][]byte

	// Maximum number of messages to keep in history
	MaxHistory int

	// Hub ID (for identification purposes)
	ID string

	// Lock for concurrent access to the Clients map
	mutex sync.RWMutex
}

// SystemMessage creates a new system message
type SystemMessage struct {
	Type   string      `json:"type"`
	Event  string      `json:"event"`
	Time   time.Time   `json:"time"`
	Data   interface{} `json:"data,omitempty"`
}

// NewHub creates a new hub with default settings
func NewHub(id string) *Hub {
	return &Hub{
		Broadcast:      make(chan []byte),
		Register:       make(chan *Client),
		Unregister:     make(chan *Client),
		Clients:        make(map[*Client]bool),
		MessageHistory: make([][]byte, 0),
		MaxHistory:     100, // Keep last 100 messages
		ID:             id,
	}
}

// Run starts the hub's main loop
func (h *Hub) Run() {
	for {
		select {
		case client := <-h.Register:
			// Add client to the map
			h.mutex.Lock()
			h.Clients[client] = true
			h.mutex.Unlock()
			
			// Send client the message history
			h.sendMessageHistory(client)
			
			// Broadcast join event
			h.broadcastSystemEvent("user_joined", map[string]interface{}{
				"user": client.Info,
			})
			
		case client := <-h.Unregister:
			// Check if client is registered
			h.mutex.Lock()
			if _, ok := h.Clients[client]; ok {
				delete(h.Clients, client)
				close(client.Send)
				
				// Broadcast leave event
				h.broadcastSystemEvent("user_left", map[string]interface{}{
					"user": client.Info,
				})
			}
			h.mutex.Unlock()
			
		case message := <-h.Broadcast:
			// Store message in history if enabled
			if h.MaxHistory > 0 {
				h.addToMessageHistory(message)
			}
			
			// Broadcast message to all clients
			h.mutex.RLock()
			for client := range h.Clients {
				select {
				case client.Send <- message:
				default:
					// If client's buffer is full, remove them
					h.mutex.RUnlock()
					h.mutex.Lock()
					delete(h.Clients, client)
					close(client.Send)
					h.mutex.Unlock()
					h.mutex.RLock()
				}
			}
			h.mutex.RUnlock()
		}
	}
}

// addToMessageHistory adds a message to the history, maintaining MaxHistory limit
func (h *Hub) addToMessageHistory(message []byte) {
	if len(h.MessageHistory) >= h.MaxHistory {
		// Remove oldest message (at index 0)
		h.MessageHistory = h.MessageHistory[1:]
	}
	
	// Add new message to the end
	h.MessageHistory = append(h.MessageHistory, message)
}

// sendMessageHistory sends the message history to a newly connected client
func (h *Hub) sendMessageHistory(client *Client) {
	// Create a system message with history
	historyMessage := SystemMessage{
		Type:  "system",
		Event: "history",
		Time:  time.Now(),
		Data: map[string]interface{}{
			"messages": h.MessageHistory,
		},
	}
	
	// Marshal the history message
	historyBytes, err := json.Marshal(historyMessage)
	if err != nil {
		fmt.Printf("error encoding history: %v", err)
		return
	}
	
	// Send history to the client
	select {
	case client.Send <- historyBytes:
	default:
		// If client's buffer is full, we can't send history
	}
}

// broadcastSystemEvent sends a system event to all clients
func (h *Hub) broadcastSystemEvent(event string, data interface{}) {
	// Create a system message
	systemMessage := SystemMessage{
		Type:  "system",
		Event: event,
		Time:  time.Now(),
		Data:  data,
	}
	
	// Marshal the system message
	systemBytes, err := json.Marshal(systemMessage)
	if err != nil {
		fmt.Printf("error encoding system event: %v", err)
		return
	}
	
	// Broadcast to all clients
	h.mutex.RLock()
	for client := range h.Clients {
		select {
		case client.Send <- systemBytes:
		default:
			// If client's buffer is full, skip this client
		}
	}
	h.mutex.RUnlock()
}

// GetClientCount returns the number of connected clients
func (h *Hub) GetClientCount() int {
	h.mutex.RLock()
	defer h.mutex.RUnlock()
	return len(h.Clients)
}

// GetClientByID finds a client by their ID
func (h *Hub) GetClientByID(id string) *Client {
	h.mutex.RLock()
	defer h.mutex.RUnlock()
	
	for client := range h.Clients {
		if client.Info.ID == id {
			return client
		}
	}
	
	return nil
}

// GetClientsByUserID finds all clients for a given user ID
func (h *Hub) GetClientsByUserID(userID string) []*Client {
	h.mutex.RLock()
	defer h.mutex.RUnlock()
	
	var clients []*Client
	for client := range h.Clients {
		if client.Info.UserID == userID {
			clients = append(clients, client)
		}
	}
	
	return clients
}

// BroadcastToUser sends a message to all clients of a specific user
func (h *Hub) BroadcastToUser(userID string, message []byte) {
	clients := h.GetClientsByUserID(userID)
	
	for _, client := range clients {
		select {
		case client.Send <- message:
		default:
			// If client's buffer is full, skip this client
		}
	}
}

// ClearMessageHistory clears the message history
func (h *Hub) ClearMessageHistory() {
	h.MessageHistory = make([][]byte, 0)
}