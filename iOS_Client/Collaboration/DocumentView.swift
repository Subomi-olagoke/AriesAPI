import SwiftUI

struct DocumentView: View {
    // MARK: - State Variables
    @State private var documentText: String = ""
    @State private var documentTitle: String = "Untitled Document"
    @State private var isEditingTitle: Bool = false
    @State private var showShareSheet: Bool = false
    @State private var focusSection: FocusSection? = .editor
    @State private var collaborators: [Collaborator] = []
    @State private var showCollaboratorsList: Bool = false
    @State private var lastSaved: Date = Date()
    @State private var isSaving: Bool = false
    @State private var editHistory: [DocumentEdit] = []
    @State private var historyIndex: Int = 0
    
    // For socket connection status
    @State private var connectionStatus: ConnectionStatus = .disconnected
    
    // MARK: - Environment
    @Environment(\.colorScheme) private var colorScheme
    
    // Define focus sections for keyboard navigation
    enum FocusSection: Hashable {
        case title, editor
    }
    
    // MARK: - Body
    var body: some View {
        VStack(spacing: 0) {
            // Top navigation bar
            topNavigationBar
            
            // Toolbar
            toolbar
            
            // Main editor
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    // Title field
                    titleField
                    
                    // Divider
                    Divider()
                        .padding(.horizontal)
                    
                    // Editor area
                    editorArea
                }
                .padding(.bottom, 60) // Space for floating UI elements
            }
            .background(backgroundColor)
            .overlay(alignment: .bottomTrailing) {
                // Floating collaboration indicators
                collaborationStatusIndicators
            }
        }
        .sheet(isPresented: $showCollaboratorsList) {
            collaboratorsView
        }
        .background(backgroundColor)
    }
    
    // MARK: - Background Color based on color scheme
    private var backgroundColor: Color {
        colorScheme == .dark ? Color(white: 0.1) : Color(white: 0.98)
    }
    
    // MARK: - Top Navigation Bar
    private var topNavigationBar: some View {
        HStack {
            // Back button
            Button(action: {
                // Handle back action
            }) {
                Image(systemName: "chevron.left")
                    .foregroundColor(Color.primary.opacity(0.7))
                    .padding(8)
                    .contentShape(Rectangle())
            }
            
            Spacer()
            
            // Connection status indicator
            connectionStatusBadge
            
            // Share button
            Button(action: {
                showShareSheet = true
            }) {
                Image(systemName: "square.and.arrow.up")
                    .foregroundColor(Color.primary.opacity(0.7))
                    .padding(8)
                    .contentShape(Rectangle())
            }
            
            // Collaborators button
            Button(action: {
                showCollaboratorsList = true
            }) {
                collaboratorAvatars
            }
        }
        .padding(.horizontal)
        .padding(.vertical, 8)
        .background(colorScheme == .dark ? Color.black : Color.white)
        .overlay(
            Rectangle()
                .frame(height: 0.5)
                .foregroundColor(Color.gray.opacity(0.3)),
            alignment: .bottom
        )
    }
    
    // MARK: - Connection Status Badge
    private var connectionStatusBadge: some View {
        HStack(spacing: 6) {
            Circle()
                .fill(connectionStatus.color)
                .frame(width: 8, height: 8)
            
            if connectionStatus != .connected {
                Text(connectionStatus.text)
                    .font(.caption)
                    .foregroundColor(Color.primary.opacity(0.7))
            }
        }
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(connectionStatus != .connected ? 
                   Color.gray.opacity(0.15) : Color.clear)
        .cornerRadius(12)
        .animation(.easeInOut(duration: 0.2), value: connectionStatus)
    }
    
    // MARK: - Collaborator Avatars
    private var collaboratorAvatars: some View {
        HStack(spacing: -8) {
            ForEach(collaborators.prefix(3)) { collaborator in
                ZStack {
                    Circle()
                        .fill(collaborator.color)
                        .frame(width: 28, height: 28)
                    
                    Text(collaborator.initials)
                        .font(.caption)
                        .fontWeight(.medium)
                        .foregroundColor(.white)
                }
                .overlay(
                    Circle()
                        .stroke(colorScheme == .dark ? Color.black : Color.white, lineWidth: 1.5)
                )
            }
            
            // If we have more collaborators than shown
            if collaborators.count > 3 {
                ZStack {
                    Circle()
                        .fill(Color.gray.opacity(0.3))
                        .frame(width: 28, height: 28)
                    
                    Text("+\(collaborators.count - 3)")
                        .font(.caption)
                        .fontWeight(.medium)
                        .foregroundColor(Color.primary)
                }
                .overlay(
                    Circle()
                        .stroke(colorScheme == .dark ? Color.black : Color.white, lineWidth: 1.5)
                )
            }
            
            // Always show add button when there are no collaborators
            if collaborators.isEmpty {
                ZStack {
                    Circle()
                        .fill(Color.gray.opacity(0.15))
                        .frame(width: 28, height: 28)
                    
                    Image(systemName: "person.badge.plus")
                        .font(.system(size: 12))
                        .foregroundColor(Color.primary.opacity(0.8))
                }
            }
        }
        .padding(.leading, 8)
    }
    
    // MARK: - Toolbar
    private var toolbar: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                // Style buttons
                toolbarButtonWithMenu(
                    icon: "textformat.size", 
                    menuItems: [
                        ToolbarMenuItem(title: "Heading 1", action: { applyStyle(style: .h1) }),
                        ToolbarMenuItem(title: "Heading 2", action: { applyStyle(style: .h2) }),
                        ToolbarMenuItem(title: "Heading 3", action: { applyStyle(style: .h3) }),
                        ToolbarMenuItem(title: "Body", action: { applyStyle(style: .body) })
                    ]
                )
                
                toolbarDivider
                
                // Basic formatting
                toolbarButton(icon: "bold", action: { toggleBold() })
                toolbarButton(icon: "italic", action: { toggleItalic() })
                toolbarButton(icon: "underline", action: { toggleUnderline() })
                
                toolbarDivider
                
                // Alignment
                toolbarButtonWithMenu(
                    icon: "text.alignleft", 
                    menuItems: [
                        ToolbarMenuItem(title: "Left", icon: "text.alignleft", action: { setAlignment(.left) }),
                        ToolbarMenuItem(title: "Center", icon: "text.aligncenter", action: { setAlignment(.center) }),
                        ToolbarMenuItem(title: "Right", icon: "text.alignright", action: { setAlignment(.right) }),
                        ToolbarMenuItem(title: "Justified", icon: "text.justify", action: { setAlignment(.justified) })
                    ]
                )
                
                toolbarDivider
                
                // List formatting
                toolbarButtonWithMenu(
                    icon: "list.bullet", 
                    menuItems: [
                        ToolbarMenuItem(title: "Bullet List", icon: "list.bullet", action: { toggleBulletList() }),
                        ToolbarMenuItem(title: "Numbered List", icon: "list.number", action: { toggleNumberedList() })
                    ]
                )
                
                toolbarDivider
                
                // History (undo/redo)
                HStack(spacing: 4) {
                    toolbarButton(
                        icon: "arrow.uturn.backward", 
                        isEnabled: historyIndex > 0,
                        action: { undo() }
                    )
                    
                    toolbarButton(
                        icon: "arrow.uturn.forward", 
                        isEnabled: historyIndex < editHistory.count - 1,
                        action: { redo() }
                    )
                }
                
                Spacer()
                
                // Last saved indicator
                HStack(spacing: 4) {
                    if isSaving {
                        ProgressView()
                            .scaleEffect(0.7)
                    } else {
                        Text("Saved")
                            .font(.caption)
                            .foregroundColor(.gray)
                    }
                }
                .padding(.trailing, 8)
            }
            .padding(.horizontal)
            .padding(.vertical, 8)
        }
        .background(colorScheme == .dark ? Color.black : Color.white)
        .overlay(
            Rectangle()
                .frame(height: 0.5)
                .foregroundColor(Color.gray.opacity(0.3)),
            alignment: .bottom
        )
    }
    
    // MARK: - Toolbar Components
    private func toolbarButton(icon: String, isEnabled: Bool = true, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: icon)
                .font(.system(size: 16))
                .foregroundColor(isEnabled ? Color.primary.opacity(0.8) : Color.gray.opacity(0.5))
                .frame(width: 34, height: 34)
                .contentShape(Rectangle())
        }
        .disabled(!isEnabled)
    }
    
    private func toolbarButtonWithMenu(icon: String, menuItems: [ToolbarMenuItem]) -> some View {
        Menu {
            ForEach(menuItems) { item in
                Button(action: item.action) {
                    if let icon = item.icon {
                        Label(item.title, systemImage: icon)
                    } else {
                        Text(item.title)
                    }
                }
            }
        } label: {
            Image(systemName: icon)
                .font(.system(size: 16))
                .foregroundColor(Color.primary.opacity(0.8))
                .frame(width: 34, height: 34)
                .contentShape(Rectangle())
        }
    }
    
    private var toolbarDivider: some View {
        Rectangle()
            .fill(Color.gray.opacity(0.3))
            .frame(width: 1, height: 24)
    }
    
    // MARK: - Title Field
    private var titleField: some View {
        HStack {
            if isEditingTitle {
                TextField("Document title", text: $documentTitle)
                    .font(.title3)
                    .fontWeight(.medium)
                    .padding(.horizontal)
                    .focused($focusSection, equals: .title)
                    .submitLabel(.done)
                    .onSubmit {
                        isEditingTitle = false
                        focusSection = .editor
                    }
            } else {
                Text(documentTitle)
                    .font(.title3)
                    .fontWeight(.medium)
                    .padding(.horizontal)
                    .onTapGesture {
                        isEditingTitle = true
                        focusSection = .title
                    }
            }
            
            Spacer()
        }
        .padding(.top, 16)
    }
    
    // MARK: - Editor Area
    private var editorArea: some View {
        TextEditor(text: $documentText)
            .font(.body)
            .padding(.horizontal)
            .frame(minHeight: 300)
            .background(Color.clear)
            .focused($focusSection, equals: .editor)
            .onChange(of: documentText) { newValue in
                // Record history
                addHistoryEntry()
                
                // Auto-save
                scheduleSave()
            }
    }
    
    // MARK: - Collaboration Status Indicators
    private var collaborationStatusIndicators: some View {
        VStack(alignment: .trailing, spacing: 8) {
            // Active users indicator
            if !collaborators.isEmpty {
                HStack(spacing: 4) {
                    Image(systemName: "eye")
                        .font(.caption)
                    Text("\(collaborators.count) viewing")
                        .font(.caption)
                }
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(Color.gray.opacity(0.15))
                .cornerRadius(12)
            }
            
            // Saving/saved indicator
            if isSaving {
                HStack(spacing: 4) {
                    ProgressView()
                        .scaleEffect(0.5)
                    Text("Saving...")
                        .font(.caption)
                }
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(Color.gray.opacity(0.15))
                .cornerRadius(12)
                .animation(.easeInOut, value: isSaving)
            }
        }
        .padding(16)
    }
    
    // MARK: - Collaborators View
    private var collaboratorsView: some View {
        VStack(spacing: 20) {
            // Title
            HStack {
                Text("Collaborators")
                    .font(.headline)
                
                Spacer()
                
                Button(action: {
                    showCollaboratorsList = false
                }) {
                    Image(systemName: "xmark")
                        .font(.subheadline)
                        .foregroundColor(.gray)
                }
            }
            .padding(.horizontal)
            .padding(.top)
            
            // List of collaborators
            if collaborators.isEmpty {
                VStack(spacing: 12) {
                    Image(systemName: "person.2.slash")
                        .font(.largeTitle)
                        .foregroundColor(.gray)
                        .padding()
                        
                    Text("No one else is viewing this document")
                        .font(.callout)
                        .foregroundColor(.gray)
                }
                .frame(maxHeight: .infinity)
            } else {
                List {
                    ForEach(collaborators) { collaborator in
                        HStack(spacing: 12) {
                            // Avatar
                            ZStack {
                                Circle()
                                    .fill(collaborator.color)
                                    .frame(width: 36, height: 36)
                                
                                Text(collaborator.initials)
                                    .font(.caption)
                                    .fontWeight(.medium)
                                    .foregroundColor(.white)
                            }
                            
                            // Name and status
                            VStack(alignment: .leading, spacing: 2) {
                                Text(collaborator.name)
                                    .font(.body)
                                
                                HStack(spacing: 4) {
                                    Circle()
                                        .fill(collaborator.isActive ? Color.green : Color.gray)
                                        .frame(width: 6, height: 6)
                                    
                                    Text(collaborator.isActive ? "Active now" : "Idle")
                                        .font(.caption)
                                        .foregroundColor(.gray)
                                }
                            }
                            
                            Spacer()
                            
                            // Permission indicator
                            Text(collaborator.permissionLevel)
                                .font(.caption)
                                .foregroundColor(.gray)
                                .padding(.horizontal, 6)
                                .padding(.vertical, 2)
                                .background(Color.gray.opacity(0.1))
                                .cornerRadius(4)
                        }
                        .padding(.vertical, 4)
                    }
                }
            }
            
            // Invite button
            Button(action: {
                // Show invite dialog
            }) {
                HStack {
                    Image(systemName: "person.badge.plus")
                    Text("Invite Collaborators")
                }
                .font(.body)
                .fontWeight(.medium)
                .foregroundColor(.white)
                .padding()
                .frame(maxWidth: .infinity)
                .background(Color.primary)
                .cornerRadius(12)
                .padding(.horizontal)
                .padding(.bottom)
            }
        }
        .presentationDetents([.medium, .large])
    }
    
    // MARK: - Functions
    
    private func toggleBold() {
        // Apply bold formatting to selected text
    }
    
    private func toggleItalic() {
        // Apply italic formatting to selected text
    }
    
    private func toggleUnderline() {
        // Apply underline formatting to selected text
    }
    
    private func setAlignment(_ alignment: TextAlignment) {
        // Apply text alignment to selected text or paragraph
    }
    
    private func toggleBulletList() {
        // Toggle bullet list for selected paragraphs
    }
    
    private func toggleNumberedList() {
        // Toggle numbered list for selected paragraphs
    }
    
    private func applyStyle(style: TextStyle) {
        // Apply text style to selected text
    }
    
    private func addHistoryEntry() {
        // Trim future history if we're in the middle
        if historyIndex < editHistory.count - 1 {
            editHistory.removeSubrange((historyIndex + 1)...)
        }
        
        // Add new entry
        let newEdit = DocumentEdit(
            text: documentText,
            timestamp: Date()
        )
        
        editHistory.append(newEdit)
        historyIndex = editHistory.count - 1
    }
    
    private func undo() {
        guard historyIndex > 0 else { return }
        
        historyIndex -= 1
        documentText = editHistory[historyIndex].text
    }
    
    private func redo() {
        guard historyIndex < editHistory.count - 1 else { return }
        
        historyIndex += 1
        documentText = editHistory[historyIndex].text
    }
    
    private var saveTimer: Timer?
    
    private func scheduleSave() {
        // Cancel existing timer
        saveTimer?.invalidate()
        
        // Schedule new save
        saveTimer = Timer.scheduledTimer(withTimeInterval: 2.0, repeats: false) { _ in
            saveDocument()
        }
    }
    
    private func saveDocument() {
        isSaving = true
        
        // Simulate network delay
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.5) {
            // Update last saved time
            self.lastSaved = Date()
            self.isSaving = false
            
            // Send to server (would be implemented with actual API call)
            self.sendToServer()
        }
    }
    
    private func sendToServer() {
        // This would send the document state to the server
        // Using websockets or REST API
    }
}

// MARK: - Supporting Types

// Connection status for the document
enum ConnectionStatus {
    case connected
    case connecting
    case disconnected
    case reconnecting
    
    var text: String {
        switch self {
        case .connected: return "Connected"
        case .connecting: return "Connecting..."
        case .disconnected: return "Offline"
        case .reconnecting: return "Reconnecting..."
        }
    }
    
    var color: Color {
        switch self {
        case .connected: return Color.green
        case .connecting, .reconnecting: return Color.orange
        case .disconnected: return Color.red
        }
    }
}

// Text style options
enum TextStyle {
    case h1, h2, h3, body
}

// Toolbar menu item
struct ToolbarMenuItem: Identifiable {
    let id = UUID()
    let title: String
    let icon: String?
    let action: () -> Void
    
    init(title: String, icon: String? = nil, action: @escaping () -> Void) {
        self.title = title
        self.icon = icon
        self.action = action
    }
}

// Collaborator model
struct Collaborator: Identifiable {
    let id: String
    let name: String
    let isActive: Bool
    let color: Color
    let permissionLevel: String // e.g., "Viewer", "Editor", "Owner"
    
    var initials: String {
        let components = name.components(separatedBy: " ")
        if components.count > 1 {
            return "\(components[0].prefix(1))\(components[1].prefix(1))"
        } else {
            return String(name.prefix(2))
        }
    }
}

// Document edit history entry
struct DocumentEdit {
    let text: String
    let timestamp: Date
}

#Preview {
    DocumentView()
}