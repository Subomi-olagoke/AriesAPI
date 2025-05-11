package main

import (
	"flag"
	"fmt"
	"os"
	"time"

	"github.com/gofiber/fiber/v2"
	"github.com/gofiber/fiber/v2/middleware/cors"
	"github.com/gofiber/fiber/v2/middleware/logger"
	"github.com/gofiber/websocket/v2"
	
	"github.com/subomi/AriesAPI/CoreTraits/handlers"
)

type Response struct {
	Message string `json:"message"`
}

var (
	addr = flag.String("addr", ":"+os.Getenv("PORT"), "Server Address")
	cert = flag.String("cert", "", "")
	key  = flag.String("key", "", "")
)

func main() {
	flag.Parse()

	app := fiber.New()
	app.Use(cors.New())
	app.Use(logger.New())

	// Welcome and status endpoints
	app.Get("/", handlers.Home)
	app.Get("/health", handlers.Health)
	app.Get("/stats", handlers.Stats)
	app.Get("/docs", handlers.Documentation)
	
	// Room endpoints
	app.Get("/rooms", handlers.GetActiveRooms)
	app.Get("/room/create", handlers.RoomCreate)
	app.Get("/room/:uuid", handlers.Room)
	app.Get("/room/:uuid/websocket", websocket.New(handlers.RoomWebsocket, websocket.Config{
		HandshakeTimeout: 10 * time.Second,
	}))
	app.Get("/room/:uuid/chat", handlers.RoomChat)
	app.Get("/room/:uuid/chat/websocket", websocket.New(handlers.RoomWebsocket))
	app.Get("/room/:uuid/viewer/websocket", websocket.New(handlers.RoomViewerWebsocket))
	
	// Streaming endpoints
	app.Get("/streams", handlers.GetActiveStreams)
	app.Get("/stream/create", handlers.CreateStream)
	app.Get("/stream/:ssuid", handlers.Stream)
	app.Get("/stream/:ssuid/websocket", websocket.New(handlers.StreamWebsocket))
	app.Get("/stream/:ssuid/chat/websocket", websocket.New(handlers.StreamChatWebsocket))
	app.Get("/stream/:ssuid/viewer/websocket", websocket.New(handlers.StreamViewerWebsocket))
	
	// Catch-all for 404s
	app.Use(handlers.NotFound)

	// Start the Fiber app using the specified address
	if err := app.Listen(*addr); err != nil {
		panic(err)
	}

	fmt.Println("Go server started on", *addr)
}