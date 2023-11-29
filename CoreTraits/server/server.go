package main

import (
	"flag"
	"fmt"
	"os"

	"github.com/gofiber/fiber/v2"
	"github.com/gofiber/fiber/v2/middleware/cors"
	"github.com/gofiber/fiber/v2/middleware/logger"
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

	// Fiber route to handle /test endpoint
	app.Get("/test", func(c *fiber.Ctx) error {
		// Your Go feature logic
		jsonResponse := Response{
			Message: "Hello from Go feature!",
		}

		// Set Content-Type header to application/json
		c.Set("Content-Type", "application/json")

		// Send JSON response
		return c.JSON(jsonResponse)
	})

	// Start the Fiber app using the specified address
	if err := app.Listen(*addr); err != nil {
		panic(err)
	}

	fmt.Println("Go server started on", *addr)
}

// Routes

/*
	    app.Get("/room/create", handlers.RoomCreate)
		app.Get("/room/:uuid", handlers.Room)
		app.Get("/room/:uuid/websocket", websocket.New(handlers.RoomWebsocket, websocket.config{
            HandshakeTimeout:10*time.second,
        }))
		app.Get("/room/:uuid/chat", handlers.RoomChat)
		app.Get("/room/:uuid/chat/websocket", websocket.New(handlers.RoomWebsocket))
		app.Get("/room/:uuid/viewer/websocket", websocket.New(handlers.RoomViewerWebsocket))
		app.Get("/stream/ssuid", handlers.Stream)
		app.Get("/stream/:ssuid/websocket", websocket.New(handlers.StreamWebsocket))
		app.Get("/stream/:ssuid/chat/websocket", websocket.New(handlers.StreamChatWebsocket))
		app.Get("/stream/:ssuid/viewer/websocket", websocket.New(handlers.StreamViewerWebsocket))
*/

// Start the Fiber app

/*func main() {

	http.HandleFunc("/test", func(w http.ResponseWriter, r *http.Request) {
		// Your Go feature logic
		jsonResponse := Response{
			Message: "Hello from Go feature!"}
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(jsonResponse)
	})
	fmt.Println("Go server started on :8000")

	http.ListenAndServe(":8000", nil)

	if err := run(); err != nil {
		// Handle the error
		panic(err)
	}
}*/
