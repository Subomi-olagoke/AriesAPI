package handlers

import (
	"fmt"
	"time"

	"github.com/gofiber/fiber/v2"
	"github.com/gofiber/websocket/v2"
	"github.com/google/uuid"
)

// StreamManager handles the management of streaming sessions
type StreamManager struct {
	Streams map[string]*Stream
}

// Stream represents a streaming session
type Stream struct {
	ID         string
	UserID     string
	Username   string
	CreatedAt  time.Time
	Status     string // "live", "ended"
	ViewerHub  *Hub   // Hub for viewers
	ChatHub    *Hub   // Hub for chat messages
	Settings   StreamSettings
	Viewers    map[string]*Viewer
	Statistics StreamStatistics
}

// StreamSettings represents configuration for a stream
type StreamSettings struct {
	Title       string `json:"title"`
	Description string `json:"description"`
	EnableChat  bool   `json:"enable_chat"`
	IsPrivate   bool   `json:"is_private"`
	MaxViewers  int    `json:"max_viewers"`
}

// StreamStatistics tracks viewer metrics
type StreamStatistics struct {
	PeakViewers     int       `json:"peak_viewers"`
	TotalViewers    int       `json:"total_viewers"`
	StreamStartTime time.Time `json:"stream_start_time"`
	StreamEndTime   time.Time `json:"stream_end_time"`
}

// Viewer represents a stream viewer
type Viewer struct {
	ID       string
	UserID   string
	Username string
	JoinedAt time.Time
}

// Global instance of the StreamManager
var streamManager = &StreamManager{
	Streams: make(map[string]*Stream),
}

// Stream handler shows the stream page
func Stream(c *fiber.Ctx) error {
	streamID := c.Params("ssuid")
	stream, exists := streamManager.Streams[streamID]
	
	if !exists {
		return c.Status(404).JSON(fiber.Map{
			"success": false,
			"message": "Stream not found",
		})
	}
	
	// Return stream details
	return c.JSON(fiber.Map{
		"stream_id":   streamID,
		"user_id":     stream.UserID,
		"username":    stream.Username,
		"created_at":  stream.CreatedAt,
		"status":      stream.Status,
		"settings":    stream.Settings,
		"viewer_count": len(stream.Viewers),
		"statistics":  stream.Statistics,
	})
}

// CreateStream creates a new streaming session
func CreateStream(c *fiber.Ctx) error {
	// Extract user info from request
	userID := c.Query("user_id")
	username := c.Query("username")
	
	if userID == "" || username == "" {
		return c.Status(400).JSON(fiber.Map{
			"success": false,
			"message": "User ID and username are required",
		})
	}
	
	// Generate a new stream ID
	streamID := uuid.New().String()
	
	// Create hubs for viewers and chat
	viewerHub := &Hub{
		Clients:    make(map[*Client]bool),
		Broadcast:  make(chan []byte),
		Register:   make(chan *Client),
		Unregister: make(chan *Client),
	}
	
	chatHub := &Hub{
		Clients:    make(map[*Client]bool),
		Broadcast:  make(chan []byte),
		Register:   make(chan *Client),
		Unregister: make(chan *Client),
	}
	
	// Start the hubs
	go viewerHub.Run()
	go chatHub.Run()
	
	// Create default stream settings
	settings := StreamSettings{
		Title:       fmt.Sprintf("%s's Stream", username),
		Description: "Live stream",
		EnableChat:  true,
		IsPrivate:   false,
		MaxViewers:  100,
	}
	
	// Create a new stream
	stream := &Stream{
		ID:        streamID,
		UserID:    userID,
		Username:  username,
		CreatedAt: time.Now(),
		Status:    "live",
		ViewerHub: viewerHub,
		ChatHub:   chatHub,
		Settings:  settings,
		Viewers:   make(map[string]*Viewer),
		Statistics: StreamStatistics{
			PeakViewers:     0,
			TotalViewers:    0,
			StreamStartTime: time.Now(),
		},
	}
	
	// Register the stream
	streamManager.Streams[streamID] = stream
	
	return c.JSON(fiber.Map{
		"success":   true,
		"stream_id": streamID,
	})
}

// EndStream ends a streaming session
func EndStream(c *fiber.Ctx) error {
	streamID := c.Params("ssuid")
	userID := c.Query("user_id")
	
	stream, exists := streamManager.Streams[streamID]
	if !exists {
		return c.Status(404).JSON(fiber.Map{
			"success": false,
			"message": "Stream not found",
		})
	}
	
	// Verify that the user is the streamer
	if stream.UserID != userID {
		return c.Status(403).JSON(fiber.Map{
			"success": false,
			"message": "Only the streamer can end the stream",
		})
	}
	
	// Update stream status
	stream.Status = "ended"
	stream.Statistics.StreamEndTime = time.Now()
	
	// Notify all viewers that the stream has ended
	endMessage := fmt.Sprintf(`{"event":"stream_ended","data":{"stream_id":"%s"}}`, streamID)
	stream.ViewerHub.Broadcast <- []byte(endMessage)
	
	return c.JSON(fiber.Map{
		"success": true,
		"message": "Stream ended successfully",
	})
}

// UpdateStreamSettings updates the stream settings
func UpdateStreamSettings(c *fiber.Ctx) error {
	streamID := c.Params("ssuid")
	userID := c.Query("user_id")
	
	stream, exists := streamManager.Streams[streamID]
	if !exists {
		return c.Status(404).JSON(fiber.Map{
			"success": false,
			"message": "Stream not found",
		})
	}
	
	// Verify that the user is the streamer
	if stream.UserID != userID {
		return c.Status(403).JSON(fiber.Map{
			"success": false,
			"message": "Only the streamer can update settings",
		})
	}
	
	// Parse and update settings
	var settings StreamSettings
	if err := c.BodyParser(&settings); err != nil {
		return c.Status(400).JSON(fiber.Map{
			"success": false,
			"message": "Invalid settings format",
		})
	}
	
	// Update settings
	stream.Settings = settings
	
	// Notify all viewers about the settings update
	updateMessage := fmt.Sprintf(`{"event":"settings_updated","data":{"stream_id":"%s","settings":%+v}}`, 
		streamID, settings)
	stream.ViewerHub.Broadcast <- []byte(updateMessage)
	
	return c.JSON(fiber.Map{
		"success":  true,
		"message":  "Stream settings updated",
		"settings": settings,
	})
}

// StreamWebsocket handles WebSocket connections for the streamer
func StreamWebsocket(c *websocket.Conn) {
	streamID := c.Params("ssuid")
	userID := c.Query("user_id")
	username := c.Query("username")
	
	// Validate query parameters
	if userID == "" || username == "" {
		c.Close()
		return
	}
	
	// Check if stream exists
	stream, exists := streamManager.Streams[streamID]
	if !exists {
		// Create a new stream if it doesn't exist
		viewerHub := &Hub{
			Clients:    make(map[*Client]bool),
			Broadcast:  make(chan []byte),
			Register:   make(chan *Client),
			Unregister: make(chan *Client),
		}
		
		chatHub := &Hub{
			Clients:    make(map[*Client]bool),
			Broadcast:  make(chan []byte),
			Register:   make(chan *Client),
			Unregister: make(chan *Client),
		}
		
		// Start the hubs
		go viewerHub.Run()
		go chatHub.Run()
		
		stream = &Stream{
			ID:        streamID,
			UserID:    userID,
			Username:  username,
			CreatedAt: time.Now(),
			Status:    "live",
			ViewerHub: viewerHub,
			ChatHub:   chatHub,
			Viewers:   make(map[string]*Viewer),
			Settings: StreamSettings{
				Title:       fmt.Sprintf("%s's Stream", username),
				Description: "Live stream",
				EnableChat:  true,
				IsPrivate:   false,
				MaxViewers:  100,
			},
			Statistics: StreamStatistics{
				PeakViewers:     0,
				TotalViewers:    0,
				StreamStartTime: time.Now(),
			},
		}
		
		streamManager.Streams[streamID] = stream
	} else if stream.UserID != userID {
		// Verify that the user is the streamer
		c.Close()
		return
	}
	
	// Create a new client for the streamer
	client := &Client{
		ID:     userID,
		UserID: userID,
		Hub:    stream.ViewerHub,
		Conn:   c.Conn,
		Send:   make(chan []byte, 256),
	}
	
	// Register the client with the hub
	client.Hub.Register <- client
	
	// Notify viewers that the streamer has connected
	startMessage := fmt.Sprintf(`{"event":"streamer_connected","data":{"stream_id":"%s","user_id":"%s","username":"%s"}}`, 
		streamID, userID, username)
	stream.ViewerHub.Broadcast <- []byte(startMessage)
	
	// Start the client read/write pumps
	go client.writePump()
	client.readPump()
}

// StreamViewerWebsocket handles WebSocket connections for stream viewers
func StreamViewerWebsocket(c *websocket.Conn) {
	streamID := c.Params("ssuid")
	userID := c.Query("user_id")
	username := c.Query("username")
	
	if userID == "" || username == "" {
		c.Close()
		return
	}
	
	// Check if stream exists
	stream, exists := streamManager.Streams[streamID]
	if !exists || stream.Status == "ended" {
		c.Close()
		return
	}
	
	// Check if the stream has reached max viewers
	if len(stream.Viewers) >= stream.Settings.MaxViewers {
		c.Close()
		return
	}
	
	// Create a new viewer
	viewerID := uuid.New().String()
	viewer := &Viewer{
		ID:       viewerID,
		UserID:   userID,
		Username: username,
		JoinedAt: time.Now(),
	}
	
	// Register the viewer
	stream.Viewers[viewerID] = viewer
	
	// Update statistics
	stream.Statistics.TotalViewers++
	if len(stream.Viewers) > stream.Statistics.PeakViewers {
		stream.Statistics.PeakViewers = len(stream.Viewers)
	}
	
	// Create a new client for the viewer
	client := &Client{
		ID:     viewerID,
		UserID: userID,
		Hub:    stream.ViewerHub,
		Conn:   c.Conn,
		Send:   make(chan []byte, 256),
	}
	
	// Register the client with the hub
	client.Hub.Register <- client
	
	// Notify about the new viewer
	joinMessage := fmt.Sprintf(`{"event":"viewer_joined","data":{"viewer_id":"%s","user_id":"%s","username":"%s"}}`, 
		viewerID, userID, username)
	stream.ViewerHub.Broadcast <- []byte(joinMessage)
	
	// Start the client read/write pumps
	go client.writePump()
	
	defer func() {
		client.Hub.Unregister <- client
		
		// Remove viewer when they disconnect
		delete(stream.Viewers, viewerID)
		
		// Notify about viewer leaving
		leaveMessage := fmt.Sprintf(`{"event":"viewer_left","data":{"viewer_id":"%s","user_id":"%s","username":"%s"}}`, 
			viewerID, userID, username)
		stream.ViewerHub.Broadcast <- []byte(leaveMessage)
	}()
	
	// Keep the connection open and handle incoming messages
	for {
		_, _, err := c.Conn.ReadMessage()
		if err != nil {
			break
		}
		// Viewers typically don't send messages through this connection
		// They use the chat connection instead
	}
}

// StreamChatWebsocket handles WebSocket connections for stream chat
func StreamChatWebsocket(c *websocket.Conn) {
	streamID := c.Params("ssuid")
	userID := c.Query("user_id")
	username := c.Query("username")
	
	if userID == "" || username == "" {
		c.Close()
		return
	}
	
	// Check if stream exists
	stream, exists := streamManager.Streams[streamID]
	if !exists || stream.Status == "ended" {
		c.Close()
		return
	}
	
	// Check if chat is enabled
	if !stream.Settings.EnableChat {
		c.Close()
		return
	}
	
	// Create a new client for chat
	clientID := uuid.New().String()
	client := &Client{
		ID:     clientID,
		UserID: userID,
		Hub:    stream.ChatHub,
		Conn:   c.Conn,
		Send:   make(chan []byte, 256),
	}
	
	// Register the client with the hub
	client.Hub.Register <- client
	
	// Start the client read/write pumps
	go client.writePump()
	
	defer func() {
		client.Hub.Unregister <- client
	}()
	
	// Handle incoming chat messages
	for {
		_, message, err := c.Conn.ReadMessage()
		if err != nil {
			break
		}
		
		// Format the message with user info before broadcasting
		// In a real implementation, you would parse the message and validate it
		chatMessage := fmt.Sprintf(`{"event":"chat_message","data":{"user_id":"%s","username":"%s","message":%s}}`, 
			userID, username, string(message))
		
		// Broadcast the chat message to all chat clients
		stream.ChatHub.Broadcast <- []byte(chatMessage)
	}
}