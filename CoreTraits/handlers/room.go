package handlers

import (
	"fmt"
	"time"

	"github.com/gofiber/fiber/v2"
	"github.com/gofiber/websocket/v2"
	"github.com/google/uuid"
	"github.com/gorilla/websocket"
)

// RoomManager handles the management of WebRTC rooms
type RoomManager struct {
	Rooms map[string]*Room
}

// Room represents a WebRTC meeting room
type Room struct {
	ID        string
	CreatedAt time.Time
	Peers     map[string]*Peer
	Hub       *Hub
}

// Peer represents a WebRTC peer connection
type Peer struct {
	ID        string
	UserID    string
	Username  string
	Role      string // "moderator" or "participant"
	Conn      *websocket.Conn
	Room      *Room
	IsAlive   bool
	Settings  PeerSettings
}

// PeerSettings represents user device settings
type PeerSettings struct {
	Video       bool `json:"video"`
	Audio       bool `json:"audio"`
	ScreenShare bool `json:"screen_share"`
}

// Hub represents the broadcasting hub for a room
type Hub struct {
	Clients    map[*Client]bool
	Broadcast  chan []byte
	Register   chan *Client
	Unregister chan *Client
}

// Client represents a connected WebSocket client
type Client struct {
	ID     string
	UserID string
	Hub    *Hub
	Conn   *websocket.Conn
	Send   chan []byte
}

// NewRoomManager creates a new RoomManager
func NewRoomManager() *RoomManager {
	return &RoomManager{
		Rooms: make(map[string]*Room),
	}
}

// Global instance of the RoomManager
var roomManager = NewRoomManager()

// RoomCreate creates a new room
func RoomCreate(c *fiber.Ctx) error {
	roomID := uuid.New().String()
	
	// Create a new hub for the room
	hub := &Hub{
		Clients:    make(map[*Client]bool),
		Broadcast:  make(chan []byte),
		Register:   make(chan *Client),
		Unregister: make(chan *Client),
	}
	
	// Create the room
	room := &Room{
		ID:        roomID,
		CreatedAt: time.Now(),
		Peers:     make(map[string]*Peer),
		Hub:       hub,
	}
	
	// Register the room
	roomManager.Rooms[roomID] = room
	
	// Start the hub
	go hub.Run()
	
	return c.JSON(fiber.Map{
		"success": true,
		"room_id": roomID,
	})
}

// Room displays info about a room
func Room(c *fiber.Ctx) error {
	roomID := c.Params("uuid")
	room, exists := roomManager.Rooms[roomID]
	
	if !exists {
		return c.Status(404).JSON(fiber.Map{
			"success": false,
			"message": "Room not found",
		})
	}
	
	// Get peer count
	peerCount := len(room.Peers)
	
	return c.JSON(fiber.Map{
		"success":    true,
		"room_id":    roomID,
		"peer_count": peerCount,
		"created_at": room.CreatedAt,
	})
}

// RoomWebsocket handles WebSocket connections to a room
func RoomWebsocket(c *websocket.Conn) {
	// Extract roomID from URL parameters
	roomID := c.Params("uuid")
	userID := c.Query("user_id")
	username := c.Query("username")
	role := c.Query("role")
	
	if userID == "" || username == "" {
		c.Close()
		return
	}
	
	// Validate role
	if role != "moderator" && role != "participant" {
		role = "participant" // Default role
	}
	
	// Check if room exists
	room, exists := roomManager.Rooms[roomID]
	if !exists {
		c.Close()
		return
	}
	
	// Create new peer
	peerID := uuid.New().String()
	peer := &Peer{
		ID:       peerID,
		UserID:   userID,
		Username: username,
		Role:     role,
		Room:     room,
		IsAlive:  true,
		Settings: PeerSettings{
			Video:       true,
			Audio:       true,
			ScreenShare: false,
		},
	}
	
	// Create new client
	client := &Client{
		ID:     peerID,
		UserID: userID,
		Hub:    room.Hub,
		Conn:   c.Conn,
		Send:   make(chan []byte, 256),
	}
	
	// Register the client with the hub
	client.Hub.Register <- client
	
	// Register the peer with the room
	room.Peers[peerID] = peer
	
	// Broadcast new peer joined
	joinMessage := fmt.Sprintf(`{"event":"peer_joined","data":{"peer_id":"%s","user_id":"%s","username":"%s","role":"%s"}}`, 
		peerID, userID, username, role)
	room.Hub.Broadcast <- []byte(joinMessage)
	
	// Start the client read/write pumps
	go client.writePump()
	client.readPump()
}

// Run starts the hub
func (h *Hub) Run() {
	for {
		select {
		case client := <-h.Register:
			h.Clients[client] = true
		case client := <-h.Unregister:
			if _, ok := h.Clients[client]; ok {
				delete(h.Clients, client)
				close(client.Send)
			}
		case message := <-h.Broadcast:
			for client := range h.Clients {
				select {
				case client.Send <- message:
				default:
					close(client.Send)
					delete(h.Clients, client)
				}
			}
		}
	}
}

// readPump reads messages from the client
func (c *Client) readPump() {
	defer func() {
		c.Hub.Unregister <- c
		c.Conn.Close()
		
		// Remove peer from room
		room := roomManager.Rooms[c.Hub.ID]
		if room != nil {
			delete(room.Peers, c.ID)
			
			// Broadcast peer left
			leftMessage := fmt.Sprintf(`{"event":"peer_left","data":{"peer_id":"%s","user_id":"%s"}}`, 
				c.ID, c.UserID)
			room.Hub.Broadcast <- []byte(leftMessage)
		}
	}()
	
	c.Conn.SetReadLimit(512 * 1024) // 512KB
	c.Conn.SetReadDeadline(time.Now().Add(60 * time.Second))
	c.Conn.SetPongHandler(func(string) error { 
		c.Conn.SetReadDeadline(time.Now().Add(60 * time.Second))
		return nil 
	})
	
	for {
		_, message, err := c.Conn.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
				fmt.Printf("error: %v", err)
			}
			break
		}
		
		// Broadcast the message
		c.Hub.Broadcast <- message
	}
}

// writePump writes messages to the client
func (c *Client) writePump() {
	ticker := time.NewTicker(54 * time.Second)
	defer func() {
		ticker.Stop()
		c.Conn.Close()
	}()
	
	for {
		select {
		case message, ok := <-c.Send:
			c.Conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
			if !ok {
				// The hub closed the channel
				c.Conn.WriteMessage(websocket.CloseMessage, []byte{})
				return
			}
			
			w, err := c.Conn.NextWriter(websocket.TextMessage)
			if err != nil {
				return
			}
			w.Write(message)
			
			// Add queued messages
			n := len(c.Send)
			for i := 0; i < n; i++ {
				w.Write([]byte{'\n'})
				w.Write(<-c.Send)
			}
			
			if err := w.Close(); err != nil {
				return
			}
		case <-ticker.C:
			c.Conn.SetWriteDeadline(time.Now().Add(10 * time.Second))
			if err := c.Conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}

// RoomChat handles the chat functionality for a room
func RoomChat(c *fiber.Ctx) error {
	roomID := c.Params("uuid")
	room, exists := roomManager.Rooms[roomID]
	
	if !exists {
		return c.Status(404).JSON(fiber.Map{
			"success": false,
			"message": "Room not found",
		})
	}
	
	return c.Render("room_chat", fiber.Map{
		"RoomID": roomID,
	})
}

// RoomViewerWebsocket handles WebSocket connections for viewers
func RoomViewerWebsocket(c *websocket.Conn) {
	// Similar to RoomWebsocket but for viewers only (no WebRTC)
	roomID := c.Params("uuid")
	userID := c.Query("user_id")
	username := c.Query("username")
	
	if userID == "" || username == "" {
		c.Close()
		return
	}
	
	// Check if room exists
	room, exists := roomManager.Rooms[roomID]
	if !exists {
		c.Close()
		return
	}
	
	// Create new client for the viewer
	clientID := uuid.New().String()
	client := &Client{
		ID:     clientID,
		UserID: userID,
		Hub:    room.Hub,
		Conn:   c.Conn,
		Send:   make(chan []byte, 256),
	}
	
	// Register the client with the hub
	client.Hub.Register <- client
	
	// Broadcast new viewer joined
	joinMessage := fmt.Sprintf(`{"event":"viewer_joined","data":{"viewer_id":"%s","user_id":"%s","username":"%s"}}`, 
		clientID, userID, username)
	room.Hub.Broadcast <- []byte(joinMessage)
	
	// Start the client read/write pumps
	go client.writePump()
	client.readPump()
}