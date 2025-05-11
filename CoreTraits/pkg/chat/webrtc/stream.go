package webrtc

import (
	"encoding/json"
	"fmt"
	"log"
	"sync"
	"time"

	"github.com/pion/webrtc/v3"
)

// StreamConfig contains configuration for a WebRTC stream
type StreamConfig struct {
	MaxViewers      int           `json:"max_viewers"`
	Lifetime        time.Duration `json:"lifetime"`
	EnableChat      bool          `json:"enable_chat"`
	EnableRecording bool          `json:"enable_recording"`
	IsPrivate       bool          `json:"is_private"`
	AccessCode      string        `json:"access_code,omitempty"`
	VideoCodec      string        `json:"video_codec"`
	AudioCodec      string        `json:"audio_codec"`
}

// Stream represents a WebRTC broadcast stream
type Stream struct {
	// Stream identity
	ID        string
	UserID    string
	Username  string
	Title     string
	CreatedAt time.Time
	
	// Stream configuration
	Config StreamConfig
	
	// Peer management
	Broadcaster       *Peer
	Viewers           map[string]*Peer
	PeerManager       *PeerManager
	
	// Media tracks
	VideoTrack *webrtc.TrackLocalStaticRTP
	AudioTrack *webrtc.TrackLocalStaticRTP
	
	// Signal channel for WebRTC signaling
	SignalChannel chan *SignalMessage
	
	// Stream state
	IsActive  bool
	ExpiresAt time.Time
	
	// Lock for concurrent access
	mutex sync.RWMutex
	
	// Stats
	Stats StreamStats
	
	// Callbacks
	OnViewerJoinCallback  func(viewerID string)
	OnViewerLeaveCallback func(viewerID string)
	OnChatMessageCallback func(viewerID, message string)
}

// StreamStats tracks stream statistics
type StreamStats struct {
	PeakViewers       int       `json:"peak_viewers"`
	TotalViewers      int       `json:"total_viewers"`
	StreamStartTime   time.Time `json:"stream_start_time"`
	StreamDuration    int64     `json:"stream_duration_seconds"`
	CurrentBandwidth  int       `json:"current_bandwidth_kbps"`
	TotalBytesStreamed int64     `json:"total_bytes_streamed"`
}

// StreamEvent represents an event in a stream
type StreamEvent struct {
	Type      string                 `json:"type"`
	Stream    *StreamInfo            `json:"stream"`
	Viewer    *PeerInfo              `json:"viewer,omitempty"`
	Timestamp time.Time              `json:"timestamp"`
	Data      map[string]interface{} `json:"data,omitempty"`
}

// StreamInfo contains information about a stream
type StreamInfo struct {
	ID        string    `json:"id"`
	UserID    string    `json:"user_id"`
	Username  string    `json:"username"`
	Title     string    `json:"title"`
	CreatedAt time.Time `json:"created_at"`
}

// NewStream creates a new WebRTC stream
func NewStream(id, userID, username, title string, config StreamConfig) *Stream {
	stream := &Stream{
		ID:            id,
		UserID:        userID,
		Username:      username,
		Title:         title,
		CreatedAt:     time.Now(),
		Config:        config,
		Viewers:       make(map[string]*Peer),
		SignalChannel: make(chan *SignalMessage, 100),
		IsActive:      true,
		Stats: StreamStats{
			PeakViewers:       0,
			TotalViewers:      0,
			StreamStartTime:   time.Now(),
			StreamDuration:    0,
			CurrentBandwidth:  0,
			TotalBytesStreamed: 0,
		},
	}
	
	// Set expiration time if lifetime is set
	if config.Lifetime > 0 {
		stream.ExpiresAt = stream.CreatedAt.Add(config.Lifetime)
	}
	
	// Create the peer manager
	stream.PeerManager = NewPeerManager(nil) // Stream doesn't use the room interface
	
	// Start the signaling loop
	go stream.signalLoop()
	
	return stream
}

// signalLoop handles WebRTC signaling
func (s *Stream) signalLoop() {
	for signal := range s.SignalChannel {
		if err := s.processSignal(signal); err != nil {
			log.Printf("Error processing signal: %v", err)
		}
	}
}

// processSignal processes a WebRTC signaling message
func (s *Stream) processSignal(signal *SignalMessage) error {
	// Check if stream is active
	if !s.IsActive {
		return fmt.Errorf("stream is no longer active")
	}
	
	// Handle the signal based on its type
	switch signal.Type {
	case "offer":
		// Only the broadcaster should send offers
		if signal.FromPeer == s.Broadcaster.ID {
			// Forward the offer to the specified viewer
			return s.forwardOfferToViewer(signal)
		} else {
			// Viewer initiated an offer, create an answer
			return s.handleViewerOffer(signal)
		}
	case "answer":
		// Forward the answer to the broadcaster
		return s.forwardAnswerToBroadcaster(signal)
	case "ice-candidate":
		// Forward ICE candidates to the appropriate peer
		return s.forwardICECandidate(signal)
	default:
		return fmt.Errorf("unknown signal type: %s", signal.Type)
	}
}

// SetBroadcaster sets the broadcaster peer
func (s *Stream) SetBroadcaster(peerID, userID, username string) (*Peer, error) {
	s.mutex.Lock()
	defer s.mutex.Unlock()
	
	// Check if broadcaster already set
	if s.Broadcaster != nil {
		return nil, fmt.Errorf("broadcaster already set")
	}
	
	// Create the broadcaster peer
	peer, err := s.PeerManager.CreatePeer(peerID, userID, username)
	if err != nil {
		return nil, err
	}
	
	// Set up media tracks
	videoTrack, err := webrtc.NewTrackLocalStaticRTP(
		webrtc.RTPCodecCapability{MimeType: "video/" + s.Config.VideoCodec},
		"video", "video",
	)
	if err != nil {
		return nil, fmt.Errorf("failed to create video track: %v", err)
	}
	
	audioTrack, err := webrtc.NewTrackLocalStaticRTP(
		webrtc.RTPCodecCapability{MimeType: "audio/" + s.Config.AudioCodec},
		"audio", "audio",
	)
	if err != nil {
		return nil, fmt.Errorf("failed to create audio track: %v", err)
	}
	
	// Store the tracks
	s.VideoTrack = videoTrack
	s.AudioTrack = audioTrack
	peer.LocalTracks["video"] = videoTrack
	peer.LocalTracks["audio"] = audioTrack
	
	// Set as broadcaster
	s.Broadcaster = peer
	
	// Create a stream start event
	event := &StreamEvent{
		Type:      "stream_start",
		Stream:    &StreamInfo{ID: s.ID, UserID: userID, Username: username, Title: s.Title, CreatedAt: s.CreatedAt},
		Timestamp: time.Now(),
	}
	
	// Broadcast the event
	s.broadcastEvent(event)
	
	return peer, nil
}

// AddViewer adds a new viewer to the stream
func (s *Stream) AddViewer(viewerID, userID, username string) (*Peer, error) {
	s.mutex.Lock()
	defer s.mutex.Unlock()
	
	// Check if stream is active
	if !s.IsActive {
		return nil, fmt.Errorf("stream is no longer active")
	}
	
	// Check if stream is full
	if s.Config.MaxViewers > 0 && len(s.Viewers) >= s.Config.MaxViewers {
		return nil, fmt.Errorf("stream is full")
	}
	
	// Create the viewer peer
	peer, err := s.PeerManager.CreatePeer(viewerID, userID, username)
	if err != nil {
		return nil, err
	}
	
	// Store the viewer
	s.Viewers[viewerID] = peer
	
	// Update stats
	s.Stats.TotalViewers++
	if len(s.Viewers) > s.Stats.PeakViewers {
		s.Stats.PeakViewers = len(s.Viewers)
	}
	
	// Call the viewer join callback if set
	if s.OnViewerJoinCallback != nil {
		s.OnViewerJoinCallback(viewerID)
	}
	
	// Create a viewer join event
	event := &StreamEvent{
		Type:      "viewer_join",
		Stream:    &StreamInfo{ID: s.ID, UserID: s.UserID, Username: s.Username, Title: s.Title, CreatedAt: s.CreatedAt},
		Viewer:    &PeerInfo{ID: viewerID, UserID: userID, Username: username},
		Timestamp: time.Now(),
	}
	
	// Broadcast the event
	s.broadcastEvent(event)
	
	return peer, nil
}

// RemoveViewer removes a viewer from the stream
func (s *Stream) RemoveViewer(viewerID string) error {
	s.mutex.Lock()
	defer s.mutex.Unlock()
	
	// Check if viewer exists
	viewer, exists := s.Viewers[viewerID]
	if !exists {
		return fmt.Errorf("viewer %s not found", viewerID)
	}
	
	// Close the peer connection
	if err := viewer.Connection.Close(); err != nil {
		log.Printf("Error closing viewer connection: %v", err)
	}
	
	// Remove the viewer
	delete(s.Viewers, viewerID)
	
	// Call the viewer leave callback if set
	if s.OnViewerLeaveCallback != nil {
		s.OnViewerLeaveCallback(viewerID)
	}
	
	// Create a viewer leave event
	event := &StreamEvent{
		Type:      "viewer_leave",
		Stream:    &StreamInfo{ID: s.ID, UserID: s.UserID, Username: s.Username, Title: s.Title, CreatedAt: s.CreatedAt},
		Viewer:    &PeerInfo{ID: viewerID, UserID: viewer.UserID, Username: viewer.Username},
		Timestamp: time.Now(),
	}
	
	// Broadcast the event
	s.broadcastEvent(event)
	
	return nil
}

// Close ends the stream and disconnects all viewers
func (s *Stream) Close() {
	s.mutex.Lock()
	defer s.mutex.Unlock()
	
	// Set stream as inactive
	s.IsActive = false
	
	// Update stats
	s.Stats.StreamDuration = int64(time.Since(s.Stats.StreamStartTime).Seconds())
	
	// Close broadcaster connection
	if s.Broadcaster != nil {
		_ = s.Broadcaster.Connection.Close()
	}
	
	// Close all viewer connections
	for _, viewer := range s.Viewers {
		_ = viewer.Connection.Close()
	}
	
	// Close the signal channel
	close(s.SignalChannel)
	
	// Create a stream end event
	event := &StreamEvent{
		Type:      "stream_end",
		Stream:    &StreamInfo{ID: s.ID, UserID: s.UserID, Username: s.Username, Title: s.Title, CreatedAt: s.CreatedAt},
		Timestamp: time.Now(),
		Data: map[string]interface{}{
			"duration":        s.Stats.StreamDuration,
			"peak_viewers":    s.Stats.PeakViewers,
			"total_viewers":   s.Stats.TotalViewers,
			"bytes_streamed": s.Stats.TotalBytesStreamed,
		},
	}
	
	// Broadcast the event
	s.broadcastEvent(event)
}

// SendSignal sends a WebRTC signaling message
func (s *Stream) SendSignal(signal *SignalMessage) {
	// Send the signal
	select {
	case s.SignalChannel <- signal:
		// Signal sent successfully
	default:
		// Signal channel is full or closed
		log.Printf("Failed to send signal: channel full or closed")
	}
}

// forwardOfferToViewer forwards an offer to a specific viewer
func (s *Stream) forwardOfferToViewer(signal *SignalMessage) error {
	// Check if viewer exists
	s.mutex.RLock()
	viewer, exists := s.Viewers[signal.ToPeer]
	s.mutex.RUnlock()
	if !exists {
		return fmt.Errorf("viewer %s not found", signal.ToPeer)
	}
	
	// Parse the SDP offer
	var offer webrtc.SessionDescription
	if err := json.Unmarshal(signal.Data, &offer); err != nil {
		return fmt.Errorf("failed to parse offer: %v", err)
	}
	
	// Set the remote description on the viewer connection
	if err := viewer.Connection.SetRemoteDescription(offer); err != nil {
		return fmt.Errorf("failed to set remote description: %v", err)
	}
	
	// Create an answer
	answer, err := viewer.Connection.CreateAnswer(nil)
	if err != nil {
		return fmt.Errorf("failed to create answer: %v", err)
	}
	
	// Set the local description
	if err := viewer.Connection.SetLocalDescription(answer); err != nil {
		return fmt.Errorf("failed to set local description: %v", err)
	}
	
	// Marshal the answer
	answerBytes, err := json.Marshal(answer)
	if err != nil {
		return fmt.Errorf("failed to marshal answer: %v", err)
	}
	
	// Create a signal for the answer
	answerSignal := &SignalMessage{
		Type:      "answer",
		FromPeer:  signal.ToPeer,
		ToPeer:    signal.FromPeer,
		SessionID: signal.SessionID,
		Data:      answerBytes,
	}
	
	// Send the answer
	s.SendSignal(answerSignal)
	
	return nil
}

// handleViewerOffer processes an offer from a viewer
func (s *Stream) handleViewerOffer(signal *SignalMessage) error {
	// Check if viewer exists
	s.mutex.RLock()
	viewer, exists := s.Viewers[signal.FromPeer]
	s.mutex.RUnlock()
	if !exists {
		return fmt.Errorf("viewer %s not found", signal.FromPeer)
	}
	
	// Parse the SDP offer
	var offer webrtc.SessionDescription
	if err := json.Unmarshal(signal.Data, &offer); err != nil {
		return fmt.Errorf("failed to parse offer: %v", err)
	}
	
	// Set the remote description on the viewer connection
	if err := viewer.Connection.SetRemoteDescription(offer); err != nil {
		return fmt.Errorf("failed to set remote description: %v", err)
	}
	
	// Add tracks to the viewer connection
	if s.VideoTrack != nil {
		if _, err := viewer.Connection.AddTrack(s.VideoTrack); err != nil {
			return fmt.Errorf("failed to add video track: %v", err)
		}
	}
	
	if s.AudioTrack != nil {
		if _, err := viewer.Connection.AddTrack(s.AudioTrack); err != nil {
			return fmt.Errorf("failed to add audio track: %v", err)
		}
	}
	
	// Create an answer
	answer, err := viewer.Connection.CreateAnswer(nil)
	if err != nil {
		return fmt.Errorf("failed to create answer: %v", err)
	}
	
	// Set the local description
	if err := viewer.Connection.SetLocalDescription(answer); err != nil {
		return fmt.Errorf("failed to set local description: %v", err)
	}
	
	// Marshal the answer
	answerBytes, err := json.Marshal(answer)
	if err != nil {
		return fmt.Errorf("failed to marshal answer: %v", err)
	}
	
	// Create a signal for the answer
	answerSignal := &SignalMessage{
		Type:      "answer",
		FromPeer:  signal.ToPeer,
		ToPeer:    signal.FromPeer,
		SessionID: signal.SessionID,
		Data:      answerBytes,
	}
	
	// Send the answer
	s.SendSignal(answerSignal)
	
	return nil
}

// forwardAnswerToBroadcaster forwards an answer to the broadcaster
func (s *Stream) forwardAnswerToBroadcaster(signal *SignalMessage) error {
	s.mutex.RLock()
	broadcaster := s.Broadcaster
	s.mutex.RUnlock()
	
	// Check if broadcaster exists
	if broadcaster == nil {
		return fmt.Errorf("broadcaster not set")
	}
	
	// Parse the SDP answer
	var answer webrtc.SessionDescription
	if err := json.Unmarshal(signal.Data, &answer); err != nil {
		return fmt.Errorf("failed to parse answer: %v", err)
	}
	
	// Set the remote description on the broadcaster connection
	if err := broadcaster.Connection.SetRemoteDescription(answer); err != nil {
		return fmt.Errorf("failed to set remote description: %v", err)
	}
	
	return nil
}

// forwardICECandidate forwards an ICE candidate to the appropriate peer
func (s *Stream) forwardICECandidate(signal *SignalMessage) error {
	// Determine the target peer
	var targetPeer *Peer
	
	s.mutex.RLock()
	if signal.ToPeer == s.Broadcaster.ID {
		// Candidate for broadcaster
		targetPeer = s.Broadcaster
	} else {
		// Candidate for viewer
		var exists bool
		targetPeer, exists = s.Viewers[signal.ToPeer]
		if !exists {
			s.mutex.RUnlock()
			return fmt.Errorf("target peer %s not found", signal.ToPeer)
		}
	}
	s.mutex.RUnlock()
	
	// Parse the ICE candidate
	var candidate webrtc.ICECandidateInit
	if err := json.Unmarshal(signal.Data, &candidate); err != nil {
		return fmt.Errorf("failed to parse ICE candidate: %v", err)
	}
	
	// Add the ICE candidate to the peer connection
	if err := targetPeer.Connection.AddICECandidate(candidate); err != nil {
		return fmt.Errorf("failed to add ICE candidate: %v", err)
	}
	
	return nil
}

// ProcessChatMessage processes a chat message from a viewer
func (s *Stream) ProcessChatMessage(viewerID string, message string) {
	s.mutex.RLock()
	viewer, exists := s.Viewers[viewerID]
	s.mutex.RUnlock()
	
	if !exists {
		log.Printf("Chat message from unknown viewer %s", viewerID)
		return
	}
	
	// Call the chat message callback if set
	if s.OnChatMessageCallback != nil {
		s.OnChatMessageCallback(viewerID, message)
	}
	
	// Create a chat message event
	event := &StreamEvent{
		Type:      "chat_message",
		Stream:    &StreamInfo{ID: s.ID, UserID: s.UserID, Username: s.Username, Title: s.Title, CreatedAt: s.CreatedAt},
		Viewer:    &PeerInfo{ID: viewerID, UserID: viewer.UserID, Username: viewer.Username},
		Timestamp: time.Now(),
		Data: map[string]interface{}{
			"message": message,
		},
	}
	
	// Broadcast the event
	s.broadcastEvent(event)
}

// broadcastEvent broadcasts an event to all connected peers
func (s *Stream) broadcastEvent(event *StreamEvent) {
	// Convert event to JSON
	eventBytes, err := json.Marshal(event)
	if err != nil {
		log.Printf("Failed to marshal event: %v", err)
		return
	}
	
	s.mutex.RLock()
	defer s.mutex.RUnlock()
	
	// Send to broadcaster if available
	if s.Broadcaster != nil && s.Broadcaster.DataChannel != nil {
		_ = s.Broadcaster.DataChannel.Send(eventBytes)
	}
	
	// Send to all viewers
	for _, viewer := range s.Viewers {
		if viewer.DataChannel != nil {
			_ = viewer.DataChannel.Send(eventBytes)
		}
	}
}

// GetStats returns the current stream statistics
func (s *Stream) GetStats() StreamStats {
	s.mutex.RLock()
	defer s.mutex.RUnlock()
	
	// Update duration
	s.Stats.StreamDuration = int64(time.Since(s.Stats.StreamStartTime).Seconds())
	
	return s.Stats
}

// GetViewers returns the current viewers
func (s *Stream) GetViewers() []*Peer {
	s.mutex.RLock()
	defer s.mutex.RUnlock()
	
	viewers := make([]*Peer, 0, len(s.Viewers))
	for _, viewer := range s.Viewers {
		viewers = append(viewers, viewer)
	}
	
	return viewers
}

// GetViewerCount returns the current number of viewers
func (s *Stream) GetViewerCount() int {
	s.mutex.RLock()
	defer s.mutex.RUnlock()
	
	return len(s.Viewers)
}

// IsExpired checks if the stream has expired
func (s *Stream) IsExpired() bool {
	// Stream never expires if lifetime is 0
	if s.Config.Lifetime == 0 {
		return false
	}
	
	// Check if current time is after expiration time
	return time.Now().After(s.ExpiresAt)
}

// SetOnViewerJoinCallback sets the callback for viewer join events
func (s *Stream) SetOnViewerJoinCallback(callback func(viewerID string)) {
	s.OnViewerJoinCallback = callback
}

// SetOnViewerLeaveCallback sets the callback for viewer leave events
func (s *Stream) SetOnViewerLeaveCallback(callback func(viewerID string)) {
	s.OnViewerLeaveCallback = callback
}

// SetOnChatMessageCallback sets the callback for chat message events
func (s *Stream) SetOnChatMessageCallback(callback func(viewerID, message string)) {
	s.OnChatMessageCallback = callback
}