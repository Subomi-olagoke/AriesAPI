package handlers

import (
	"github.com/gofiber/fiber/v2"
)

// ServerInfo contains basic information about the server
type ServerInfo struct {
	Name        string `json:"name"`
	Version     string `json:"version"`
	Description string `json:"description"`
	Features    []string `json:"features"`
}

// Home serves as the welcome page endpoint
func Home(c *fiber.Ctx) error {
	info := ServerInfo{
		Name:        "AriesAPI WebRTC Server",
		Version:     "1.0.0",
		Description: "A WebRTC server for audio/video communication and streaming",
		Features: []string{
			"WebRTC peer-to-peer communication",
			"Real-time chat",
			"Live streaming",
			"Room management",
			"Secure communications",
		},
	}

	return c.JSON(fiber.Map{
		"status":  "success",
		"message": "Welcome to AriesAPI WebRTC Server",
		"info":    info,
	})
}

// Health provides a health check endpoint
func Health(c *fiber.Ctx) error {
	// For more sophisticated health checks, you could add:
	// - Database connectivity check
	// - Available memory and CPU usage
	// - Server uptime
	
	return c.JSON(fiber.Map{
		"status":  "success",
		"message": "Server is running",
		"health":  "ok",
	})
}

// Stats returns server statistics
func Stats(c *fiber.Ctx) error {
	// Get room count
	roomCount := len(roomManager.Rooms)
	
	// Get stream count
	streamCount := len(streamManager.Streams)
	
	// Get active connections count
	activeConnections := 0
	for _, room := range roomManager.Rooms {
		activeConnections += len(room.Peers)
	}
	
	// Get active stream viewers
	activeViewers := 0
	for _, stream := range streamManager.Streams {
		if stream.Status == "live" {
			activeViewers += len(stream.Viewers)
		}
	}
	
	return c.JSON(fiber.Map{
		"status": "success",
		"stats": fiber.Map{
			"active_rooms": roomCount,
			"active_streams": streamCount,
			"active_connections": activeConnections,
			"active_viewers": activeViewers,
		},
	})
}

// NotFound handles 404 routes
func NotFound(c *fiber.Ctx) error {
	return c.Status(404).JSON(fiber.Map{
		"status":  "error",
		"message": "Route not found",
	})
}

// GetActiveRooms returns a list of active rooms
func GetActiveRooms(c *fiber.Ctx) error {
	rooms := make([]fiber.Map, 0)
	
	for id, room := range roomManager.Rooms {
		rooms = append(rooms, fiber.Map{
			"id":         id,
			"peer_count": len(room.Peers),
			"created_at": room.CreatedAt,
		})
	}
	
	return c.JSON(fiber.Map{
		"status": "success",
		"rooms":  rooms,
	})
}

// GetActiveStreams returns a list of active streams
func GetActiveStreams(c *fiber.Ctx) error {
	streams := make([]fiber.Map, 0)
	
	for id, stream := range streamManager.Streams {
		if stream.Status == "live" {
			streams = append(streams, fiber.Map{
				"id":           id,
				"user_id":      stream.UserID,
				"username":     stream.Username,
				"title":        stream.Settings.Title,
				"description":  stream.Settings.Description,
				"viewer_count": len(stream.Viewers),
				"created_at":   stream.CreatedAt,
			})
		}
	}
	
	return c.JSON(fiber.Map{
		"status":  "success",
		"streams": streams,
	})
}

// Documentation returns API documentation
func Documentation(c *fiber.Ctx) error {
	docs := []fiber.Map{
		{
			"path":        "/",
			"method":      "GET",
			"description": "Welcome page with server information",
		},
		{
			"path":        "/health",
			"method":      "GET",
			"description": "Health check endpoint",
		},
		{
			"path":        "/stats",
			"method":      "GET",
			"description": "Server statistics",
		},
		{
			"path":        "/rooms",
			"method":      "GET",
			"description": "List of active rooms",
		},
		{
			"path":        "/streams",
			"method":      "GET",
			"description": "List of active streams",
		},
		{
			"path":        "/room/create",
			"method":      "GET",
			"description": "Create a new room",
		},
		{
			"path":        "/room/:uuid",
			"method":      "GET",
			"description": "Get information about a specific room",
		},
		{
			"path":        "/room/:uuid/websocket",
			"method":      "WebSocket",
			"description": "WebSocket connection for room participants",
		},
		{
			"path":        "/room/:uuid/chat",
			"method":      "GET",
			"description": "Chat page for a room",
		},
		{
			"path":        "/room/:uuid/chat/websocket",
			"method":      "WebSocket",
			"description": "WebSocket connection for room chat",
		},
		{
			"path":        "/room/:uuid/viewer/websocket",
			"method":      "WebSocket",
			"description": "WebSocket connection for room viewers",
		},
		{
			"path":        "/stream/create",
			"method":      "GET",
			"description": "Create a new stream",
		},
		{
			"path":        "/stream/:ssuid",
			"method":      "GET",
			"description": "Get information about a specific stream",
		},
		{
			"path":        "/stream/:ssuid/websocket",
			"method":      "WebSocket",
			"description": "WebSocket connection for stream broadcaster",
		},
		{
			"path":        "/stream/:ssuid/chat/websocket",
			"method":      "WebSocket",
			"description": "WebSocket connection for stream chat",
		},
		{
			"path":        "/stream/:ssuid/viewer/websocket",
			"method":      "WebSocket",
			"description": "WebSocket connection for stream viewers",
		},
	}
	
	return c.JSON(fiber.Map{
		"status":        "success",
		"api_version":   "1.0.0",
		"documentation": docs,
	})
}