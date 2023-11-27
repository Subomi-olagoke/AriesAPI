package server

import (
	"flag"
	"os"

	"github.com/gofiber/websocket/v2"
)

var (
	addr = flag.String("addr", ":"+os.Getenv("PORT"), "Server Address")
	cert = flag.String("cert", "", "")
	key  = flag.String("key", "", "")
)

func run() error {
	flag.Parse()

	if *addr == ":" {
		*addr = ":5000"
	}

	app.Get("/", handlers.Welcome)
	app.Get("/room/create", handlers.RoomCreate)
	app.Get("/room/:uuid", handlers.Room)
	app.Get("/room/:uuid/websocket")
	app.Get("/room/:uuid/chat", handlers.RoomChat)
	app.Get("/room/:uuid/chat/websocket", websocket.New(handlers.RoomWebsocket))
	app.Get("/room/:uuid/viewer/websocket", websocket.New(handlers.RoomViewerWebsocket))
	app.Get("/stream/ssuid", handlers.Stream)
	app.Get("/stream/:ssuid/websocket")
	app.Get("/stream/:ssuid/chat/websocket")
	app.Get("/stream/:ssuid/viewer/websocket")

	return app.Listen(*addr)
}

func main() {
	if err := run(); err != nil {
		// Handle the error
		panic(err)
	}
}
