package webrtc

import (
	"encoding/json"
	"fmt"
	"log"
	"sync"
	"time"

	"github.com/pion/webrtc/v3"
)

// PeerManager manages WebRTC peer connections
type PeerManager struct {
	// Lock for concurrent access
	mutex sync.RWMutex
	
	// Map of peer connections by peer ID
	peers map[string]*Peer
	
	// Configuration for WebRTC
	config webrtc.Configuration
	
	// Room this peer manager belongs to
	room *Room
	
	// MediaTrack sources
	videoTracks map[string]*webrtc.TrackLocalStaticRTP
	audioTracks map[string]*webrtc.TrackLocalStaticRTP
}

// Peer represents a WebRTC peer connection
type Peer struct {
	// Peer identity
	ID       string
	UserID   string
	Username string
	
	// Connection
	Connection *webrtc.PeerConnection
	
	// Tracks this peer is sending
	LocalTracks map[string]*webrtc.TrackLocalStaticRTP
	
	// Tracks this peer is receiving
	RemoteTracks map[string]*webrtc.TrackRemote
	
	// Data channel
	DataChannel *webrtc.DataChannel
	
	// Status
	Connected    bool
	IsPublisher  bool
	IsSubscriber bool
	JoinedAt     time.Time
	
	// Settings
	VideoEnabled  bool
	AudioEnabled  bool
	ScreenEnabled bool
}

// PeerEvent represents an event related to a peer
type PeerEvent struct {
	Type      string                 `json:"type"`
	Peer      *PeerInfo              `json:"peer"`
	Timestamp time.Time              `json:"timestamp"`
	Data      map[string]interface{} `json:"data,omitempty"`
}

// PeerInfo contains information about a peer
type PeerInfo struct {
	ID       string `json:"id"`
	UserID   string `json:"user_id"`
	Username string `json:"username"`
}

// SignalMessage represents a WebRTC signaling message
type SignalMessage struct {
	Type      string          `json:"type"`
	FromPeer  string          `json:"from_peer"`
	ToPeer    string          `json:"to_peer,omitempty"`
	SessionID string          `json:"session_id"`
	Data      json.RawMessage `json:"data"`
}

// NewPeerManager creates a new peer manager
func NewPeerManager(room *Room) *PeerManager {
	// Setup ICE servers for STUN/TURN
	iceServers := []webrtc.ICEServer{
		{
			URLs: []string{"stun:stun.l.google.com:19302"},
		},
		// Add TURN servers for production use
	}
	
	return &PeerManager{
		peers:       make(map[string]*Peer),
		config:      webrtc.Configuration{ICEServers: iceServers},
		room:        room,
		videoTracks: make(map[string]*webrtc.TrackLocalStaticRTP),
		audioTracks: make(map[string]*webrtc.TrackLocalStaticRTP),
	}
}

// CreatePeer creates a new WebRTC peer
func (pm *PeerManager) CreatePeer(id, userID, username string) (*Peer, error) {
	pm.mutex.Lock()
	defer pm.mutex.Unlock()
	
	// Check if peer already exists
	if _, exists := pm.peers[id]; exists {
		return nil, fmt.Errorf("peer with ID %s already exists", id)
	}
	
	// Create a new WebRTC peer connection
	peerConnection, err := webrtc.NewPeerConnection(pm.config)
	if err != nil {
		return nil, fmt.Errorf("failed to create peer connection: %v", err)
	}
	
	// Create a new peer
	peer := &Peer{
		ID:           id,
		UserID:       userID,
		Username:     username,
		Connection:   peerConnection,
		LocalTracks:  make(map[string]*webrtc.TrackLocalStaticRTP),
		RemoteTracks: make(map[string]*webrtc.TrackRemote),
		Connected:    false,
		IsPublisher:  false,
		IsSubscriber: true,
		JoinedAt:     time.Now(),
		VideoEnabled: true,
		AudioEnabled: true,
		ScreenEnabled: false,
	}
	
	// Set up event handlers for the peer connection
	pm.setupPeerConnectionHandlers(peer)
	
	// Store the peer
	pm.peers[id] = peer
	
	return peer, nil
}

// RemovePeer removes a peer from the manager
func (pm *PeerManager) RemovePeer(id string) error {
	pm.mutex.Lock()
	defer pm.mutex.Unlock()
	
	peer, exists := pm.peers[id]
	if !exists {
		return fmt.Errorf("peer with ID %s not found", id)
	}
	
	// Close the peer connection
	if err := peer.Connection.Close(); err != nil {
		return fmt.Errorf("failed to close peer connection: %v", err)
	}
	
	// Remove the peer from the manager
	delete(pm.peers, id)
	
	// Notify the room about the peer leaving
	pm.room.OnPeerLeave(id)
	
	return nil
}

// GetPeer returns a peer by ID
func (pm *PeerManager) GetPeer(id string) (*Peer, error) {
	pm.mutex.RLock()
	defer pm.mutex.RUnlock()
	
	peer, exists := pm.peers[id]
	if !exists {
		return nil, fmt.Errorf("peer with ID %s not found", id)
	}
	
	return peer, nil
}

// GetPeers returns all peers
func (pm *PeerManager) GetPeers() []*Peer {
	pm.mutex.RLock()
	defer pm.mutex.RUnlock()
	
	peers := make([]*Peer, 0, len(pm.peers))
	for _, peer := range pm.peers {
		peers = append(peers, peer)
	}
	
	return peers
}

// ProcessSignal processes a WebRTC signaling message
func (pm *PeerManager) ProcessSignal(signal *SignalMessage) error {
	// Handle different signal types
	switch signal.Type {
	case "offer":
		return pm.handleOffer(signal)
	case "answer":
		return pm.handleAnswer(signal)
	case "ice-candidate":
		return pm.handleICECandidate(signal)
	default:
		return fmt.Errorf("unknown signal type: %s", signal.Type)
	}
}

// handleOffer processes a WebRTC offer
func (pm *PeerManager) handleOffer(signal *SignalMessage) error {
	// Get the peer
	peer, err := pm.GetPeer(signal.ToPeer)
	if err != nil {
		return err
	}
	
	// Parse the SDP offer
	var offer webrtc.SessionDescription
	if err := json.Unmarshal(signal.Data, &offer); err != nil {
		return fmt.Errorf("failed to parse offer: %v", err)
	}
	
	// Set the remote description
	if err := peer.Connection.SetRemoteDescription(offer); err != nil {
		return fmt.Errorf("failed to set remote description: %v", err)
	}
	
	// Create an answer
	answer, err := peer.Connection.CreateAnswer(nil)
	if err != nil {
		return fmt.Errorf("failed to create answer: %v", err)
	}
	
	// Set the local description
	if err := peer.Connection.SetLocalDescription(answer); err != nil {
		return fmt.Errorf("failed to set local description: %v", err)
	}
	
	// Send the answer back
	answerBytes, err := json.Marshal(answer)
	if err != nil {
		return fmt.Errorf("failed to marshal answer: %v", err)
	}
	
	answerSignal := &SignalMessage{
		Type:      "answer",
		FromPeer:  signal.ToPeer,
		ToPeer:    signal.FromPeer,
		SessionID: signal.SessionID,
		Data:      answerBytes,
	}
	
	// Send the answer via the room
	pm.room.SendSignal(answerSignal)
	
	return nil
}

// handleAnswer processes a WebRTC answer
func (pm *PeerManager) handleAnswer(signal *SignalMessage) error {
	// Get the peer
	peer, err := pm.GetPeer(signal.ToPeer)
	if err != nil {
		return err
	}
	
	// Parse the SDP answer
	var answer webrtc.SessionDescription
	if err := json.Unmarshal(signal.Data, &answer); err != nil {
		return fmt.Errorf("failed to parse answer: %v", err)
	}
	
	// Set the remote description
	if err := peer.Connection.SetRemoteDescription(answer); err != nil {
		return fmt.Errorf("failed to set remote description: %v", err)
	}
	
	return nil
}

// handleICECandidate processes an ICE candidate
func (pm *PeerManager) handleICECandidate(signal *SignalMessage) error {
	// Get the peer
	peer, err := pm.GetPeer(signal.ToPeer)
	if err != nil {
		return err
	}
	
	// Parse the ICE candidate
	var candidate webrtc.ICECandidateInit
	if err := json.Unmarshal(signal.Data, &candidate); err != nil {
		return fmt.Errorf("failed to parse ICE candidate: %v", err)
	}
	
	// Add the ICE candidate
	if err := peer.Connection.AddICECandidate(candidate); err != nil {
		return fmt.Errorf("failed to add ICE candidate: %v", err)
	}
	
	return nil
}

// setupPeerConnectionHandlers sets up event handlers for a peer connection
func (pm *PeerManager) setupPeerConnectionHandlers(peer *Peer) {
	// Handle ICE connection state changes
	peer.Connection.OnICEConnectionStateChange(func(state webrtc.ICEConnectionState) {
		log.Printf("ICE connection state changed to %s for peer %s", state.String(), peer.ID)
		
		switch state {
		case webrtc.ICEConnectionStateConnected:
			peer.Connected = true
			pm.room.OnPeerConnected(peer.ID)
		case webrtc.ICEConnectionStateDisconnected, webrtc.ICEConnectionStateFailed, webrtc.ICEConnectionStateClosed:
			peer.Connected = false
			pm.room.OnPeerDisconnected(peer.ID)
		}
	})
	
	// Handle new ICE candidates
	peer.Connection.OnICECandidate(func(candidate *webrtc.ICECandidate) {
		if candidate == nil {
			return
		}
		
		// Convert the candidate to JSON
		candidateJSON, err := json.Marshal(candidate.ToJSON())
		if err != nil {
			log.Printf("Failed to marshal ICE candidate: %v", err)
			return
		}
		
		// Create a signaling message
		signal := &SignalMessage{
			Type:      "ice-candidate",
			FromPeer:  peer.ID,
			SessionID: pm.room.ID,
			Data:      candidateJSON,
		}
		
		// Send the ICE candidate to all other peers in the room
		pm.room.SendSignal(signal)
	})
	
	// Handle new tracks
	peer.Connection.OnTrack(func(track *webrtc.TrackRemote, receiver *webrtc.RTPReceiver) {
		log.Printf("Received track %s from peer %s", track.ID(), peer.ID)
		
		// Store the track
		peer.RemoteTracks[track.ID()] = track
		
		// Forward the track to other peers
		pm.room.OnNewTrack(peer.ID, track)
	})
	
	// Handle data channel creation
	peer.Connection.OnDataChannel(func(dataChannel *webrtc.DataChannel) {
		log.Printf("New data channel %s created for peer %s", dataChannel.Label(), peer.ID)
		
		peer.DataChannel = dataChannel
		
		// Set up data channel handlers
		dataChannel.OnOpen(func() {
			log.Printf("Data channel %s opened for peer %s", dataChannel.Label(), peer.ID)
		})
		
		dataChannel.OnMessage(func(msg webrtc.DataChannelMessage) {
			// Process data channel message
			pm.room.OnDataChannelMessage(peer.ID, msg.Data)
		})
	})
}

// AddTrack adds a media track to a peer
func (pm *PeerManager) AddTrack(peerID string, track *webrtc.TrackLocalStaticRTP) error {
	peer, err := pm.GetPeer(peerID)
	if err != nil {
		return err
	}
	
	// Add the track to the peer connection
	sender, err := peer.Connection.AddTrack(track)
	if err != nil {
		return fmt.Errorf("failed to add track: %v", err)
	}
	
	// Store the track
	peer.LocalTracks[track.ID()] = track
	
	// Handle RTP feedback
	go func() {
		for {
			// Read RTCP packets
			rtcpPackets, err := sender.ReadRTCP()
			if err != nil {
				return
			}
			
			// Process RTCP packets if needed
			for _, packet := range rtcpPackets {
				log.Printf("Received RTCP packet of type %T from peer %s", packet, peerID)
			}
		}
	}()
	
	return nil
}

// CreateDataChannel creates a data channel for a peer
func (pm *PeerManager) CreateDataChannel(peerID, label string) (*webrtc.DataChannel, error) {
	peer, err := pm.GetPeer(peerID)
	if err != nil {
		return nil, err
	}
	
	// Create the data channel
	dataChannel, err := peer.Connection.CreateDataChannel(label, nil)
	if err != nil {
		return nil, fmt.Errorf("failed to create data channel: %v", err)
	}
	
	// Store the data channel
	peer.DataChannel = dataChannel
	
	// Set up data channel handlers
	dataChannel.OnOpen(func() {
		log.Printf("Data channel %s opened for peer %s", dataChannel.Label(), peerID)
	})
	
	dataChannel.OnMessage(func(msg webrtc.DataChannelMessage) {
		// Process data channel message
		pm.room.OnDataChannelMessage(peerID, msg.Data)
	})
	
	return dataChannel, nil
}

// SendToPeer sends a message to a specific peer via data channel
func (pm *PeerManager) SendToPeer(peerID string, message []byte) error {
	peer, err := pm.GetPeer(peerID)
	if err != nil {
		return err
	}
	
	// Check if data channel exists and is open
	if peer.DataChannel == nil {
		return fmt.Errorf("peer %s has no data channel", peerID)
	}
	
	// Send the message
	if err := peer.DataChannel.Send(message); err != nil {
		return fmt.Errorf("failed to send message: %v", err)
	}
	
	return nil
}

// BroadcastToPeers sends a message to all peers via data channel
func (pm *PeerManager) BroadcastToPeers(message []byte) {
	pm.mutex.RLock()
	defer pm.mutex.RUnlock()
	
	for _, peer := range pm.peers {
		if peer.DataChannel != nil {
			_ = peer.DataChannel.Send(message)
		}
	}
}