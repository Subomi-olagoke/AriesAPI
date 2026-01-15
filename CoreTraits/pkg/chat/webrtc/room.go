package webrtc

import (
	"encoding/json"
	"fmt"
	"log"
	"sync"
	"time"

	"github.com/pion/webrtc/v3"
)

// RoomConfig contains configuration for a WebRTC room
type RoomConfig struct {
	MaxParticipants int           `json:"max_participants"`
	Lifetime        time.Duration `json:"lifetime"`
	EnableChat      bool          `json:"enable_chat"`
	EnableRecording bool          `json:"enable_recording"`
	IsPrivate       bool          `json:"is_private"`
	AccessCode      string        `json:"access_code,omitempty"`
}

// Room represents a WebRTC meeting room
type Room struct {
	// Room identity
	ID        string
	Name      string
	CreatedAt time.Time
	
	// Room configuration
	Config RoomConfig
	
	// Peer management
	PeerManager *PeerManager
	
	// Signal channel for WebRTC signaling
	SignalChannel chan *SignalMessage
	
	// Room state
	IsActive  bool
	ExpiresAt time.Time
	
	// Lock for concurrent access
	mutex sync.RWMutex
	
	// Callbacks
	OnPeerJoinCallback      func(peerID string)
	OnPeerLeaveCallback     func(peerID string)
	OnPeerConnectedCallback func(peerID string)
	OnMessageCallback       func(message []byte)
}

// RoomEvent represents an event in a room
type RoomEvent struct {
	Type      string                 `json:"type"`
	Room      *RoomInfo              `json:"room"`
	Peer      *PeerInfo              `json:"peer,omitempty"`
	Timestamp time.Time              `json:"timestamp"`
	Data      map[string]interface{} `json:"data,omitempty"`
}

// RoomInfo contains information about a room
type RoomInfo struct {
	ID        string    `json:"id"`
	Name      string    `json:"name"`
	CreatedAt time.Time `json:"created_at"`
}

// NewRoom creates a new WebRTC room
func NewRoom(id, name string, config RoomConfig) *Room {
	room := &Room{
		ID:            id,
		Name:          name,
		CreatedAt:     time.Now(),
		Config:        config,
		SignalChannel: make(chan *SignalMessage, 100),
		IsActive:      true,
	}
	
	// Set expiration time if lifetime is set
	if config.Lifetime > 0 {
		room.ExpiresAt = room.CreatedAt.Add(config.Lifetime)
	}
	
	// Create the peer manager
	room.PeerManager = NewPeerManager(room)
	
	// Start the signaling loop
	go room.signalLoop()
	
	return room
}

// signalLoop handles WebRTC signaling
func (r *Room) signalLoop() {
	for signal := range r.SignalChannel {
		if err := r.processSignal(signal); err != nil {
			log.Printf("Error processing signal: %v", err)
		}
	}
}

// processSignal processes a WebRTC signaling message
func (r *Room) processSignal(signal *SignalMessage) error {
	// Check if room is active
	if !r.IsActive {
		return fmt.Errorf("room is no longer active")
	}
	
	// Process the signal
	return r.PeerManager.ProcessSignal(signal)
}

// AddPeer adds a new peer to the room
func (r *Room) AddPeer(id, userID, username string) (*Peer, error) {
	r.mutex.Lock()
	defer r.mutex.Unlock()
	
	// Check if room is active
	if !r.IsActive {
		return nil, fmt.Errorf("room is no longer active")
	}
	
	// Check if room is full
	peerCount := len(r.PeerManager.GetPeers())
	if r.Config.MaxParticipants > 0 && peerCount >= r.Config.MaxParticipants {
		return nil, fmt.Errorf("room is full")
	}
	
	// Create the peer
	peer, err := r.PeerManager.CreatePeer(id, userID, username)
	if err != nil {
		return nil, err
	}
	
	// Call the peer join callback if set
	if r.OnPeerJoinCallback != nil {
		r.OnPeerJoinCallback(id)
	}
	
	// Create a join event
	event := &RoomEvent{
		Type:      "peer_join",
		Room:      &RoomInfo{ID: r.ID, Name: r.Name, CreatedAt: r.CreatedAt},
		Peer:      &PeerInfo{ID: id, UserID: userID, Username: username},
		Timestamp: time.Now(),
	}
	
	// Broadcast the event
	r.broadcastEvent(event)
	
	return peer, nil
}

// RemovePeer removes a peer from the room
func (r *Room) RemovePeer(id string) error {
	r.mutex.Lock()
	defer r.mutex.Unlock()
	
	// Get the peer
	peer, err := r.PeerManager.GetPeer(id)
	if err != nil {
		return err
	}
	
	// Remove the peer
	if err := r.PeerManager.RemovePeer(id); err != nil {
		return err
	}
	
	// Call the peer leave callback if set
	if r.OnPeerLeaveCallback != nil {
		r.OnPeerLeaveCallback(id)
	}
	
	// Create a leave event
	event := &RoomEvent{
		Type:      "peer_leave",
		Room:      &RoomInfo{ID: r.ID, Name: r.Name, CreatedAt: r.CreatedAt},
		Peer:      &PeerInfo{ID: id, UserID: peer.UserID, Username: peer.Username},
		Timestamp: time.Now(),
	}
	
	// Broadcast the event
	r.broadcastEvent(event)
	
	return nil
}

// Close closes the room and disconnects all peers
func (r *Room) Close() {
	r.mutex.Lock()
	defer r.mutex.Unlock()
	
	// Set room as inactive
	r.IsActive = false
	
	// Get all peers
	peers := r.PeerManager.GetPeers()
	
	// Close all peer connections
	for _, peer := range peers {
		_ = peer.Connection.Close()
	}
	
	// Close the signal channel
	close(r.SignalChannel)
	
	// Create a close event
	event := &RoomEvent{
		Type:      "room_close",
		Room:      &RoomInfo{ID: r.ID, Name: r.Name, CreatedAt: r.CreatedAt},
		Timestamp: time.Now(),
	}
	
	// Broadcast the event
	r.broadcastEvent(event)
}

// SendSignal sends a WebRTC signaling message
func (r *Room) SendSignal(signal *SignalMessage) {
	// Send the signal
	select {
	case r.SignalChannel <- signal:
		// Signal sent successfully
	default:
		// Signal channel is full or closed
		log.Printf("Failed to send signal: channel full or closed")
	}
}

// OnPeerConnected is called when a peer connects
func (r *Room) OnPeerConnected(peerID string) {
	// Call the peer connected callback if set
	if r.OnPeerConnectedCallback != nil {
		r.OnPeerConnectedCallback(peerID)
	}
	
	// Get the peer
	peer, err := r.PeerManager.GetPeer(peerID)
	if err != nil {
		log.Printf("Error getting peer %s: %v", peerID, err)
		return
	}
	
	// Create a connected event
	event := &RoomEvent{
		Type:      "peer_connected",
		Room:      &RoomInfo{ID: r.ID, Name: r.Name, CreatedAt: r.CreatedAt},
		Peer:      &PeerInfo{ID: peerID, UserID: peer.UserID, Username: peer.Username},
		Timestamp: time.Now(),
	}
	
	// Broadcast the event
	r.broadcastEvent(event)
}

// OnPeerDisconnected is called when a peer disconnects
func (r *Room) OnPeerDisconnected(peerID string) {
	// Get the peer
	peer, err := r.PeerManager.GetPeer(peerID)
	if err != nil {
		log.Printf("Error getting peer %s: %v", peerID, err)
		return
	}
	
	// Create a disconnected event
	event := &RoomEvent{
		Type:      "peer_disconnected",
		Room:      &RoomInfo{ID: r.ID, Name: r.Name, CreatedAt: r.CreatedAt},
		Peer:      &PeerInfo{ID: peerID, UserID: peer.UserID, Username: peer.Username},
		Timestamp: time.Now(),
	}
	
	// Broadcast the event
	r.broadcastEvent(event)
}

// OnPeerLeave is called when a peer leaves
func (r *Room) OnPeerLeave(peerID string) {
	// Nothing additional to do here since RemovePeer handles this
}

// OnNewTrack is called when a peer adds a new track
func (r *Room) OnNewTrack(peerID string, track *webrtc.TrackRemote) {
	// Get the peer
	sourcePeer, err := r.PeerManager.GetPeer(peerID)
	if err != nil {
		log.Printf("Error getting peer %s: %v", peerID, err)
		return
	}
	
	// Create an event for the new track
	event := &RoomEvent{
		Type:      "new_track",
		Room:      &RoomInfo{ID: r.ID, Name: r.Name, CreatedAt: r.CreatedAt},
		Peer:      &PeerInfo{ID: peerID, UserID: sourcePeer.UserID, Username: sourcePeer.Username},
		Timestamp: time.Now(),
		Data: map[string]interface{}{
			"track_id":   track.ID(),
			"track_kind": track.Kind().String(),
		},
	}
	
	// Broadcast the event
	r.broadcastEvent(event)
	
	// Forward the track to other peers
	// This would typically involve reading RTP packets from the track
	// and writing them to a local track that other peers can subscribe to
	// For brevity, this implementation is simplified
	
	log.Printf("New track %s of kind %s from peer %s", track.ID(), track.Kind().String(), peerID)
}

// OnDataChannelMessage is called when a message is received on a data channel
func (r *Room) OnDataChannelMessage(peerID string, data []byte) {
	// Get the peer
	peer, err := r.PeerManager.GetPeer(peerID)
	if err != nil {
		log.Printf("Error getting peer %s: %v", peerID, err)
		return
	}
	
	// Call the message callback if set
	if r.OnMessageCallback != nil {
		r.OnMessageCallback(data)
	}
	
	// Try to parse the message as JSON
	var message map[string]interface{}
	if err := json.Unmarshal(data, &message); err != nil {
		// Not JSON, treat as raw data
		log.Printf("Received raw data from peer %s: %d bytes", peerID, len(data))
		return
	}
	
	// Check message type
	if msgType, ok := message["type"].(string); ok {
		switch msgType {
		case "chat":
			// Handle chat message
			chatEvent := &RoomEvent{
				Type:      "chat_message",
				Room:      &RoomInfo{ID: r.ID, Name: r.Name, CreatedAt: r.CreatedAt},
				Peer:      &PeerInfo{ID: peerID, UserID: peer.UserID, Username: peer.Username},
				Timestamp: time.Now(),
				Data:      message,
			}
			r.broadcastEvent(chatEvent)
		case "status":
			// Handle status update
			statusEvent := &RoomEvent{
				Type:      "peer_status",
				Room:      &RoomInfo{ID: r.ID, Name: r.Name, CreatedAt: r.CreatedAt},
				Peer:      &PeerInfo{ID: peerID, UserID: peer.UserID, Username: peer.Username},
				Timestamp: time.Now(),
				Data:      message,
			}
			r.broadcastEvent(statusEvent)
		default:
			// Unknown message type
			log.Printf("Received unknown message type from peer %s: %s", peerID, msgType)
		}
	}
}

// broadcastEvent broadcasts an event to all peers
func (r *Room) broadcastEvent(event *RoomEvent) {
	// Convert event to JSON
	eventBytes, err := json.Marshal(event)
	if err != nil {
		log.Printf("Failed to marshal event: %v", err)
		return
	}
	
	// Broadcast to all peers
	r.PeerManager.BroadcastToPeers(eventBytes)
}

// GetPeers returns all peers in the room
func (r *Room) GetPeers() []*Peer {
	return r.PeerManager.GetPeers()
}

// GetPeerCount returns the number of peers in the room
func (r *Room) GetPeerCount() int {
	peers := r.PeerManager.GetPeers()
	return len(peers)
}

// IsExpired checks if the room has expired
func (r *Room) IsExpired() bool {
	// Room never expires if lifetime is 0
	if r.Config.Lifetime == 0 {
		return false
	}
	
	// Check if current time is after expiration time
	return time.Now().After(r.ExpiresAt)
}

// SetOnPeerJoinCallback sets the callback for peer join events
func (r *Room) SetOnPeerJoinCallback(callback func(peerID string)) {
	r.OnPeerJoinCallback = callback
}

// SetOnPeerLeaveCallback sets the callback for peer leave events
func (r *Room) SetOnPeerLeaveCallback(callback func(peerID string)) {
	r.OnPeerLeaveCallback = callback
}

// SetOnPeerConnectedCallback sets the callback for peer connected events
func (r *Room) SetOnPeerConnectedCallback(callback func(peerID string)) {
	r.OnPeerConnectedCallback = callback
}

// SetOnMessageCallback sets the callback for message events
func (r *Room) SetOnMessageCallback(callback func(message []byte)) {
	r.OnMessageCallback = callback
}