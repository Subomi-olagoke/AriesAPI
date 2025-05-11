package chat

import (
	"encoding/json"
	"fmt"
	"log"
	"time"

	"github.com/gorilla/websocket"
)

const (
	// Time allowed to write a message to the peer
	writeWait = 10 * time.Second

	// Time allowed to read the next pong message from the peer
	pongWait = 60 * time.Second

	// Send pings to peer with this period (must be less than pongWait)
	pingPeriod = (pongWait * 9) / 10

	// Maximum message size allowed from peer
	maxMessageSize = 512 * 1024 // 512KB
)

// Message represents a chat message
type Message struct {
	Type    string      `json:"type"`
	Content string      `json:"content,omitempty"`
	Sender  *ClientInfo `json:"sender"`
	Time    time.Time   `json:"time"`
	Data    interface{} `json:"data,omitempty"`
}

// ClientInfo contains client metadata
type ClientInfo struct {
	ID       string `json:"id"`
	UserID   string `json:"user_id"`
	Username string `json:"username"`
	Role     string `json:"role,omitempty"`
}

// Client is a middleman between the websocket connection and the hub
type Client struct {
	Hub *Hub

	// The websocket connection
	Conn *websocket.Conn

	// Buffered channel of outbound messages
	Send chan []byte

	// Client info for identification
	Info ClientInfo

	// IsAlive tracks if the client is active
	IsAlive bool
}

// NewClient creates a new chat client
func NewClient(hub *Hub, conn *websocket.Conn, info ClientInfo) *Client {
	return &Client{
		Hub:     hub,
		Conn:    conn,
		Send:    make(chan []byte, 256),
		Info:    info,
		IsAlive: true,
	}
}

// ReadPump pumps messages from the websocket connection to the hub
//
// The application runs readPump in a per-connection goroutine. The application
// ensures that there is at most one reader on a connection by executing all
// reads from this goroutine.
func (c *Client) ReadPump() {
	defer func() {
		c.Hub.Unregister <- c
		c.Conn.Close()
		c.IsAlive = false
	}()

	c.Conn.SetReadLimit(maxMessageSize)
	c.Conn.SetReadDeadline(time.Now().Add(pongWait))
	c.Conn.SetPongHandler(func(string) error {
		c.Conn.SetReadDeadline(time.Now().Add(pongWait))
		return nil
	})

	for {
		_, message, err := c.Conn.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
				log.Printf("websocket error: %v", err)
			}
			break
		}

		// Process message (could be JSON or other format)
		// In this example, we'll assume it's a JSON chat message
		var chatMessage Message
		
		err = json.Unmarshal(message, &chatMessage)
		if err != nil {
			// If not valid JSON, create a text message instead
			chatMessage = Message{
				Type:    "text",
				Content: string(message),
				Sender:  &c.Info,
				Time:    time.Now(),
			}
		} else {
			// Ensure sender info is correct regardless of what client sent
			chatMessage.Sender = &c.Info
			chatMessage.Time = time.Now()
		}

		// Re-encode with correct sender info
		messageBytes, err := json.Marshal(chatMessage)
		if err != nil {
			log.Printf("error encoding message: %v", err)
			continue
		}

		c.Hub.Broadcast <- messageBytes
	}
}

// WritePump pumps messages from the hub to the websocket connection
//
// A goroutine running writePump is started for each connection. The
// application ensures that there is at most one writer to a connection by
// executing all writes from this goroutine.
func (c *Client) WritePump() {
	ticker := time.NewTicker(pingPeriod)
	defer func() {
		ticker.Stop()
		c.Conn.Close()
	}()

	for {
		select {
		case message, ok := <-c.Send:
			c.Conn.SetWriteDeadline(time.Now().Add(writeWait))
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

			// Add queued messages to the current websocket message
			n := len(c.Send)
			for i := 0; i < n; i++ {
				w.Write([]byte{'\n'})
				w.Write(<-c.Send)
			}

			if err := w.Close(); err != nil {
				return
			}
		case <-ticker.C:
			c.Conn.SetWriteDeadline(time.Now().Add(writeWait))
			if err := c.Conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}

// SendEvent sends a system event to this client
func (c *Client) SendEvent(eventType string, data interface{}) {
	event := Message{
		Type: eventType,
		Time: time.Now(),
		Data: data,
	}

	eventBytes, err := json.Marshal(event)
	if err != nil {
		log.Printf("error encoding event: %v", err)
		return
	}

	select {
	case c.Send <- eventBytes:
	default:
		// Drop the message if the client's send buffer is full
		fmt.Printf("client %s send buffer full, dropping message", c.Info.ID)
	}
}