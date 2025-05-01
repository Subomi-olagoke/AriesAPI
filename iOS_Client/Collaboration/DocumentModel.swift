import Foundation
import Combine

// MARK: - Document Model
class DocumentModel: ObservableObject {
    // Document properties
    @Published var id: String?
    @Published var title: String = "Untitled Document"
    @Published var content: String = ""
    @Published var lastModified: Date = Date()
    @Published var isShared: Bool = false
    @Published var collaborators: [Collaborator] = []
    
    // Connection state
    @Published var connectionStatus: ConnectionStatus = .disconnected
    
    // Edit history
    @Published var editHistory: [DocumentEdit] = []
    @Published var historyIndex: Int = 0
    
    // Editing state
    @Published var isSaving: Bool = false
    @Published var hasPendingChanges: Bool = false
    
    // Document permissions
    @Published var userPermission: Permission = .editor
    
    private var saveDebouncer: Debouncer?
    private var webSocketManager: WebSocketManager?
    
    init(id: String? = nil) {
        self.id = id
        self.saveDebouncer = Debouncer(delay: 2.0) { [weak self] in
            self?.saveDocument()
        }
        
        // Initialize with one history entry
        addHistoryEntry()
    }
    
    // MARK: - Document Loading
    func loadDocument() {
        guard let id = id else { return }
        
        // Simulate loading document from API
        self.isSaving = true
        
        // In a real app, this would be an API call
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) { [weak self] in
            guard let self = self else { return }
            
            // Simulated document data
            self.title = "Project Proposal"
            self.content = """
            # Project Proposal
            
            ## Overview
            This document outlines the key features and timeline for our new project.
            
            ## Goals
            1. Improve user engagement
            2. Increase retention rate
            3. Expand market reach
            
            ## Timeline
            - Research phase: 2 weeks
            - Design phase: 3 weeks
            - Development phase: 8 weeks
            - Testing phase: 3 weeks
            - Launch: November 1st
            """
            
            self.lastModified = Date().addingTimeInterval(-3600) // 1 hour ago
            self.isShared = true
            
            // Simulate collaborators
            self.collaborators = [
                Collaborator(id: "user1", name: "Jane Smith", isActive: true, color: .blue, permissionLevel: "Editor"),
                Collaborator(id: "user2", name: "Alex Johnson", isActive: false, color: .green, permissionLevel: "Viewer")
            ]
            
            // Reset edit history with this content
            self.editHistory = [DocumentEdit(text: self.content, timestamp: self.lastModified)]
            self.historyIndex = 0
            
            self.isSaving = false
            self.hasPendingChanges = false
            
            // Connect to WebSocket for real-time updates
            self.connectToWebSocket()
        }
    }
    
    // MARK: - WebSocket Connection
    private func connectToWebSocket() {
        guard let id = id else { return }
        
        self.connectionStatus = .connecting
        
        // Create WebSocket connection
        // In a real app, this would connect to your WebSocket server
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.5) { [weak self] in
            guard let self = self else { return }
            
            // Simulate successful connection
            self.connectionStatus = .connected
            
            // Setup real-time updates (simulated)
            self.simulateRealtimeUpdates()
        }
    }
    
    private func simulateRealtimeUpdates() {
        // This would be replaced with actual WebSocket message handling
        // For the demo, we'll just simulate occasional updates from collaborators
        
        // Simulate a collaborator joining after 5 seconds
        DispatchQueue.main.asyncAfter(deadline: .now() + 5.0) { [weak self] in
            guard let self = self else { return }
            
            let newCollaborator = Collaborator(
                id: "user3",
                name: "Michael Chen",
                isActive: true,
                color: .purple,
                permissionLevel: "Viewer"
            )
            
            self.collaborators.append(newCollaborator)
        }
    }
    
    // MARK: - Content Editing
    func contentChanged(newContent: String) {
        self.content = newContent
        self.hasPendingChanges = true
        
        // Add history entry
        addHistoryEntry()
        
        // Schedule autosave
        saveDebouncer?.call()
    }
    
    func titleChanged(newTitle: String) {
        self.title = newTitle
        self.hasPendingChanges = true
        
        // Schedule autosave
        saveDebouncer?.call()
    }
    
    // MARK: - Document Saving
    private func saveDocument() {
        guard hasPendingChanges else { return }
        
        self.isSaving = true
        
        // In a real app, this would be an API call
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) { [weak self] in
            guard let self = self else { return }
            
            self.lastModified = Date()
            self.isSaving = false
            self.hasPendingChanges = false
        }
    }
    
    // MARK: - History Management
    func addHistoryEntry() {
        // Trim future history if we're in the middle
        if historyIndex < editHistory.count - 1 {
            editHistory.removeSubrange((historyIndex + 1)...)
        }
        
        // Add new entry
        let newEdit = DocumentEdit(
            text: content,
            timestamp: Date()
        )
        
        editHistory.append(newEdit)
        historyIndex = editHistory.count - 1
    }
    
    func undo() {
        guard historyIndex > 0 else { return }
        
        historyIndex -= 1
        content = editHistory[historyIndex].text
        hasPendingChanges = true
        
        // Schedule save
        saveDebouncer?.call()
    }
    
    func redo() {
        guard historyIndex < editHistory.count - 1 else { return }
        
        historyIndex += 1
        content = editHistory[historyIndex].text
        hasPendingChanges = true
        
        // Schedule save
        saveDebouncer?.call()
    }
    
    // MARK: - Sharing and Permissions
    func shareDocument(completion: @escaping (Result<String, Error>) -> Void) {
        // In a real app, this would call an API to generate a share link
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) {
            let shareLink = "https://ariesmvp-9903a26b3095.herokuapp.com/docs/\(self.id ?? "unknown")"
            completion(.success(shareLink))
        }
    }
    
    func updatePermission(for userId: String, to permission: Permission) {
        // Update collaborator permission
        if let index = collaborators.firstIndex(where: { $0.id == userId }) {
            var updatedCollaborator = collaborators[index]
            updatedCollaborator.permissionLevel = permission.rawValue
            collaborators[index] = updatedCollaborator
            
            // In a real app, this would call an API to update permissions
        }
    }
}

// MARK: - Supporting Types

// Helper for debouncing save operations
class Debouncer {
    private let delay: TimeInterval
    private var workItem: DispatchWorkItem?
    private let callback: () -> Void
    
    init(delay: TimeInterval, callback: @escaping () -> Void) {
        self.delay = delay
        self.callback = callback
    }
    
    func call() {
        // Cancel the previous work item if it exists
        workItem?.cancel()
        
        // Create a new work item
        let workItem = DispatchWorkItem { [weak self] in
            self?.callback()
        }
        self.workItem = workItem
        
        // Schedule the work item
        DispatchQueue.main.asyncAfter(deadline: .now() + delay, execute: workItem)
    }
}

// Document permission levels
enum Permission: String {
    case viewer = "Viewer"
    case commenter = "Commenter"
    case editor = "Editor"
    case owner = "Owner"
}

// WebSocketManager stub - would be replaced with actual implementation
class WebSocketManager {
    // This would be implemented with URLSessionWebSocketTask or a third-party library
}