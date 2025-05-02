import SwiftUI
import PhotosUI

// MARK: - Collaboration Modes
enum CollaborationMode: String, CaseIterable {
    case writing = "Writing Mode"
    case freeform = "Free Mode"

    var icon: String {
        switch self {
        case .writing: return "text.cursor"
        case .freeform: return "square.on.square"
        }
    }

    var description: String {
        switch self {
        case .writing: return "Add structured text and media content"
        case .freeform: return "Freely arrange and manipulate content"
        }
    }
}

// MARK: - Collaborative Document Element
struct CollabElement: Identifiable, Codable {
    let id = UUID()
    var mediaType: String
    var content: String
    var position: CGPoint = .zero
    var size: CGSize = CGSize(width: 200, height: 150)
    var rotation: Double = 0
    var zIndex: Double = 0
    var isSelected: Bool = false
    
    // Conversion from CollabMediaType to storage format
    init(from mediaType: CollabMediaType, position: CGPoint = .zero, size: CGSize = CGSize(width: 200, height: 150)) {
        self.position = position
        self.size = size
        
        switch mediaType {
        case .image(let image):
            self.mediaType = "image"
            if let imageData = image.jpegData(compressionQuality: 0.7)?.base64EncodedString() {
                self.content = imageData
            } else {
                self.content = ""
            }
        case .text(let text):
            self.mediaType = "text"
            self.content = text
        case .file(let url, let name):
            self.mediaType = "file"
            self.content = "\(name)|\(url.absoluteString)"
        case .drawing(let points):
            self.mediaType = "drawing"
            let pointsString = points.map { "\($0.x),\($0.y)" }.joined(separator: ";")
            self.content = pointsString
        }
    }
    
    // Conversion to CollabMediaType for display
    func toMediaType() -> CollabMediaType? {
        switch mediaType {
        case "text":
            return .text(content)
        case "image":
            if let data = Data(base64Encoded: content),
               let image = UIImage(data: data) {
                return .image(image)
            }
            return nil
        case "file":
            let components = content.split(separator: "|")
            if components.count == 2, let url = URL(string: String(components[1])) {
                return .file(url, String(components[0]))
            }
            return nil
        case "drawing":
            let pointStrings = content.split(separator: ";")
            let points = pointStrings.compactMap { pointStr -> CGPoint? in
                let coords = pointStr.split(separator: ",")
                if coords.count == 2,
                   let x = Double(coords[0]),
                   let y = Double(coords[1]) {
                    return CGPoint(x: x, y: y)
                }
                return nil
            }
            return .drawing(points)
        default:
            return nil
        }
    }
}

// MARK: - Media Types for Collaboration
enum CollabMediaType: Identifiable {
    case image(UIImage)
    case text(String)
    case file(URL, String)
    case drawing([CGPoint])

    var id: UUID { UUID() }
}

// MARK: - Document Edit History Entry
struct DocumentEdit: Codable {
    let text: String
    let timestamp: Date
    let operation: DocumentOperation
    
    enum CodingKeys: String, CodingKey {
        case text, timestamp, operation
    }
}

enum DocumentOperation: String, Codable {
    case insert, delete, format, replace, none
}

// MARK: - Collaborator Model
struct Collaborator: Identifiable, Codable, Equatable {
    let id: String
    let name: String
    let isActive: Bool
    let color: Color
    let permissionLevel: String
    let cursorPosition: Int?
    
    enum CodingKeys: String, CodingKey {
        case id, name, isActive, permissionLevel, cursorPosition
        case colorHex // For encoding/decoding Color
    }
    
    var initials: String {
        let parts = name.split(separator: " ")
        let first = parts.first?.first ?? Character(" ")
        let second = parts.dropFirst().first?.first ?? Character(" ")
        return "\(first)\(second)"
    }
    
    init(id: String, name: String, isActive: Bool, color: Color, permissionLevel: String = "Editor", cursorPosition: Int? = nil) {
        self.id = id
        self.name = name
        self.isActive = isActive
        self.color = color
        self.permissionLevel = permissionLevel
        self.cursorPosition = cursorPosition
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(String.self, forKey: .id)
        name = try container.decode(String.self, forKey: .name)
        isActive = try container.decode(Bool.self, forKey: .isActive)
        permissionLevel = try container.decode(String.self, forKey: .permissionLevel)
        cursorPosition = try container.decodeIfPresent(Int.self, forKey: .cursorPosition)
        
        // Decode color from hex
        let colorHex = try container.decode(String.self, forKey: .colorHex)
        color = Color(hex: colorHex) ?? .blue
    }
    
    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        try container.encode(id, forKey: .id)
        try container.encode(name, forKey: .name)
        try container.encode(isActive, forKey: .isActive)
        try container.encode(permissionLevel, forKey: .permissionLevel)
        try container.encodeIfPresent(cursorPosition, forKey: .cursorPosition)
        
        // Encode color as hex
        let colorHex = color.toHex() ?? "#0000FF"
        try container.encode(colorHex, forKey: .colorHex)
    }
    
    // Implement Equatable to enable animation tracking
    static func == (lhs: Collaborator, rhs: Collaborator) -> Bool {
        return lhs.id == rhs.id &&
               lhs.name == rhs.name &&
               lhs.isActive == rhs.isActive &&
               lhs.permissionLevel == rhs.permissionLevel &&
               lhs.cursorPosition == rhs.cursorPosition
    }
}

// MARK: - Focus Areas
enum FocusSection: Hashable { case title, editor }

// MARK: - Connection Status
enum ConnectionStatus {
    case connected, connecting, disconnected, reconnecting

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
        case .connected: return .green
        case .connecting, .reconnecting: return .orange
        case .disconnected: return .red
        }
    }
    
    var icon: String {
        switch self {
        case .connected: return "circle.fill"
        case .connecting: return "ellipsis.circle"
        case .disconnected: return "exclamationmark.circle"
        case .reconnecting: return "arrow.clockwise.circle"
        }
    }
}

// MARK: - Text Styles
enum TextStyle { case h1, h2, h3, body }

// MARK: - Toolbar Menu Item
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

// MARK: - Document Types
enum DocumentType: String, CaseIterable {
    case text = "Text Document"
    case notes = "Notes"
    case project = "Project Plan"
    case meeting = "Meeting Notes"
    
    var icon: String {
        switch self {
        case .text: return "doc.text"
        case .notes: return "note.text"
        case .project: return "list.bullet.clipboard"
        case .meeting: return "person.2.square.stack"
        }
    }
}

// MARK: - Document Model
struct CollaborativeDocument: Codable, Identifiable {
    var id: String
    var title: String
    var content: String
    var documentType: String
    var elements: [CollabElement]
    var collaborators: [Collaborator]
    var version: Int
    var createdAt: Date
    var updatedAt: Date
    var ownerId: String
    var spaceId: String
    
    enum CodingKeys: String, CodingKey {
        case id, title, content, elements, collaborators, version
        case documentType = "document_type"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case ownerId = "owner_id"
        case spaceId = "space_id"
    }
}

// MARK: - DocumentView
struct DocumentView: View {
    // FeedViewModel for API calls
    @StateObject private var viewModel = FeedViewModel()
    
    // Document state variables
    @State private var documentId: String?
    @State private var documentText = ""
    @State private var documentTitle = "Untitled Document"
    @State private var documentType: DocumentType = .text
    @State private var isEditingTitle = false
    @State private var showCollaboratorsList = false
    @State private var lastSaved = Date()
    @State private var isSaving = false
    @State private var connectionStatus: ConnectionStatus = .connecting
    @FocusState private var focusSection: FocusSection?
    @State private var editHistory: [DocumentEdit] = []
    @State private var historyIndex = 0
    @State private var elements: [CollabElement] = []
    @State private var activeCollaborators: [Collaborator] = []
    @State private var localUser: String = UUID().uuidString // Replace with actual user ID
    @State private var localUserName: String = "Current User" // Replace with actual user name
    @State private var localUserColor: Color = .blue
    @State private var isInitialLoad = true
    @State private var permissionLevel: String = "editor" // Default permission
    @State private var socketTask: URLSessionWebSocketTask?
    @State private var socketConnected = false
    @State private var pingTimer: Timer?
    @State private var isToolbarVisible = true
    @State private var showDocTypeMenu = false
    @State private var showSaveIndicator = false
    
    private let cornerRadius: CGFloat = 10
    private let heartbeatInterval: TimeInterval = 30

    @Environment(\.colorScheme) private var colorScheme

    private var backgroundColor: Color {
        colorScheme == .dark ? Color(white: 0.08) : Color(white: 0.98)
    }
    
    private var cardBackgroundColor: Color {
        colorScheme == .dark ? Color(white: 0.12) : Color.white
    }
    
    private var subtleTextColor: Color {
        colorScheme == .dark ? Color(white: 0.6) : Color(white: 0.5)
    }
    
    private var borderColor: Color {
        colorScheme == .dark ? Color(white: 0.2) : Color(white: 0.9)
    }

    var body: some View {
        VStack(spacing: 0) {
            topNavigationBar
            contentArea
        }
        .sheet(isPresented: $showCollaboratorsList) {
            collaboratorsView
                .presentationDetents([.medium, .large])
                .presentationDragIndicator(.visible)
                .presentationCornerRadius(cornerRadius)
        }
        .background(backgroundColor)
        .onAppear {
            if isInitialLoad {
                loadDocument()
                connectToSocket()
                isInitialLoad = false
            }
        }
        .onDisappear {
            disconnectSocket()
        }
    }
    
    // MARK: Content Area
    private var contentArea: some View {
        ZStack(alignment: .top) {
            ScrollView {
                VStack(alignment: .leading, spacing: 0) {
                    documentHeaderArea
                    
                    if isToolbarVisible {
                        toolbar
                    }
                    
                    editorArea
                }
                .animation(.easeInOut(duration: 0.2), value: isToolbarVisible)
                .padding(.bottom, 60)
            }
            
            // Floating "Show Toolbar" button that appears when toolbar is hidden
            if !isToolbarVisible {
                Button(action: { withAnimation { isToolbarVisible = true } }) {
                    Image(systemName: "chevron.down")
                        .foregroundColor(.primary)
                        .padding(8)
                        .background(cardBackgroundColor)
                        .clipShape(Circle())
                        .shadow(color: Color.black.opacity(0.1), radius: 2, x: 0, y: 1)
                }
                .padding(.top, 4)
                .transition(.move(edge: .top).combined(with: .opacity))
            }
        }
        .overlay(alignment: .bottom) {
            VStack(spacing: 8) {
                collaboratorCursors
                collaborationStatusIndicators
            }
            .padding(.bottom, 16)
        }
        .background(backgroundColor)
    }

    // MARK: Top Navigation Bar
    private var topNavigationBar: some View {
        HStack(spacing: 12) {
            // Back button
            Button(action: {}) {
                Image(systemName: "chevron.left")
                    .foregroundColor(.primary)
                    .padding(8)
                    .contentShape(Rectangle())
            }
            
            // Document type indicator
            documentTypeIndicator
            
            Spacer()
            
            // Connection status
            connectionStatusIndicator
                .padding(.trailing, 4)
            
            // Share button
            Button(action: { shareDocument() }) {
                Image(systemName: "square.and.arrow.up")
                    .foregroundColor(.primary)
                    .padding(8)
                    .contentShape(Rectangle())
            }
            
            // Collaborators button
            Button(action: { showCollaboratorsList = true }) { 
                collaboratorAvatars
            }
        }
        .padding(.horizontal, 12)
        .padding(.vertical, 8)
        .background(cardBackgroundColor)
        .overlay(
            Rectangle()
                .fill(borderColor)
                .frame(height: 1),
            alignment: .bottom
        )
    }
    
    // MARK: Document Type Indicator
    private var documentTypeIndicator: some View {
        Button(action: { showDocTypeMenu.toggle() }) {
            HStack(spacing: 6) {
                Image(systemName: documentType.icon)
                Text(documentType.rawValue)
                    .font(.footnote)
                    .foregroundColor(.primary)
                Image(systemName: "chevron.down")
                    .font(.system(size: 10))
                    .foregroundColor(subtleTextColor)
            }
            .padding(.horizontal, 8)
            .padding(.vertical, 6)
            .background(Color.primary.opacity(0.05))
            .cornerRadius(6)
        }
        .popover(isPresented: $showDocTypeMenu, arrowEdge: .bottom) {
            VStack(alignment: .leading, spacing: 0) {
                ForEach(DocumentType.allCases, id: \.self) { type in
                    Button(action: { documentType = type; showDocTypeMenu = false }) {
                        HStack {
                            Image(systemName: type.icon)
                                .frame(width: 20)
                            Text(type.rawValue)
                                .font(.body)
                            Spacer()
                            if documentType == type {
                                Image(systemName: "checkmark")
                                    .font(.footnote)
                                    .foregroundColor(.blue)
                            }
                        }
                        .padding(.vertical, 10)
                        .padding(.horizontal, 12)
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(PlainButtonStyle())
                    
                    if type != DocumentType.allCases.last {
                        Divider()
                            .padding(.horizontal, 12)
                    }
                }
            }
            .padding(.vertical, 4)
            .frame(minWidth: 180)
            .background(cardBackgroundColor)
        }
    }

    // MARK: Connection Status Indicator
    private var connectionStatusIndicator: some View {
        HStack(spacing: 6) {
            Image(systemName: connectionStatus.icon)
                .foregroundColor(connectionStatus.color)
                .font(.system(size: 10))
            
            if connectionStatus != .connected {
                Text(connectionStatus.text)
                    .font(.caption2)
                    .fontWeight(.medium)
                    .foregroundColor(subtleTextColor)
            }
        }
        .padding(.horizontal, 8)
        .padding(.vertical, 4)
        .background(connectionStatus != .connected ? 
                   Color.primary.opacity(0.05) : 
                   Color.clear)
        .cornerRadius(6)
        .animation(.easeInOut(duration: 0.2), value: connectionStatus)
    }

    // MARK: Collaborator Avatars
    private var collaboratorAvatars: some View {
        ZStack(alignment: .trailing) {
            ForEach(Array(activeCollaborators.prefix(3).enumerated()), id: \.element.id) { index, collaborator in
                collaboratorAvatar(for: collaborator)
                    .offset(x: -CGFloat(index) * 14)
                    .zIndex(Double(activeCollaborators.count - index))
            }
            
            if activeCollaborators.count > 3 {
                Text("+\(activeCollaborators.count - 3)")
                    .font(.caption2)
                    .fontWeight(.medium)
                    .foregroundColor(.white)
                    .frame(width: 28, height: 28)
                    .background(Color.gray)
                    .clipShape(Circle())
                    .overlay(
                        Circle()
                            .stroke(cardBackgroundColor, lineWidth: 1.5)
                    )
                    .offset(x: -CGFloat(3) * 14)
                    .zIndex(0)
            }
            
            // Show "add" button if no collaborators or if user has permission
            if activeCollaborators.isEmpty || permissionLevel == "owner" || permissionLevel == "admin" {
                Button(action: { showCollaboratorsList = true }) {
                    Image(systemName: "plus")
                        .font(.system(size: 12))
                        .foregroundColor(Color.primary)
                        .frame(width: 28, height: 28)
                        .background(Color.primary.opacity(0.1))
                        .clipShape(Circle())
                }
                .offset(x: activeCollaborators.isEmpty ? 0 : -CGFloat(min(activeCollaborators.count, 3)) * 14 - 14)
                .zIndex(activeCollaborators.isEmpty ? 1 : -1)
            }
        }
        .padding(.trailing, 20) // Add extra padding for offset avatars
        .frame(height: 32)
    }
    
    // Single collaborator avatar
    private func collaboratorAvatar(for collaborator: Collaborator) -> some View {
        ZStack {
            Circle()
                .fill(collaborator.color)
                .frame(width: 28, height: 28)
            
            Text(collaborator.initials)
                .font(.caption2)
                .fontWeight(.semibold)
                .foregroundColor(.white)
        }
        .overlay(
            Circle()
                .stroke(cardBackgroundColor, lineWidth: 1.5)
        )
        .overlay(
            Circle()
                .fill(collaborator.isActive ? .green : .clear)
                .frame(width: 8, height: 8)
                .overlay(
                    Circle()
                        .stroke(cardBackgroundColor, lineWidth: 1.5)
                )
            , alignment: .bottomTrailing
        )
    }
    
    // MARK: Document Header Area
    private var documentHeaderArea: some View {
        VStack(spacing: 2) {
            // Editable title
            if isEditingTitle {
                TextField("Document Title", text: $documentTitle)
                    .font(.title)
                    .fontWeight(.bold)
                    .padding(.horizontal, 16)
                    .padding(.top, 16)
                    .padding(.bottom, 4)
                    .focused($focusSection, equals: .title)
                    .onSubmit {
                        isEditingTitle = false
                        sendTitleUpdate()
                    }
                    .submitLabel(.done)
            } else {
                Text(documentTitle)
                    .font(.title)
                    .fontWeight(.bold)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .padding(.horizontal, 16)
                    .padding(.top, 16)
                    .padding(.bottom, 4)
                    .contentShape(Rectangle())
                    .onTapGesture {
                        isEditingTitle = true
                        focusSection = .title
                    }
            }
            
            // Last edited info
            HStack(spacing: 6) {
                Text("Last edited \(timeAgo(from: lastSaved))")
                    .font(.caption)
                    .foregroundColor(subtleTextColor)
                
                // Save indicator animation
                if showSaveIndicator {
                    HStack(spacing: 4) {
                        Circle()
                            .fill(Color.blue)
                            .frame(width: 4, height: 4)
                            .opacity(isSaving ? 1 : 0)
                        
                        Text(isSaving ? "Saving..." : "Saved")
                            .font(.caption)
                            .foregroundColor(subtleTextColor)
                    }
                    .transition(.opacity)
                    .animation(.easeInOut, value: isSaving)
                }
            }
            .padding(.horizontal, 16)
            .padding(.bottom, 8)
        }
    }

    // MARK: Toolbar
    private var toolbar: some View {
        VStack(spacing: 0) {
            // Hide toolbar button
            HStack {
                Spacer()
                Button(action: { withAnimation { isToolbarVisible = false } }) {
                    Image(systemName: "chevron.up")
                        .foregroundColor(subtleTextColor)
                        .padding(4)
                }
            }
            .padding(.horizontal, 16)
            .padding(.top, 2)
            .padding(.bottom, 2)
            
            Divider()
            
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 2) {
                    toolbarGroup {
                        toolbarButtonWithMenu(icon: "textformat.size", menuItems: [
                            ToolbarMenuItem(title: "Heading 1", action: { applyStyle(style: .h1) }),
                            ToolbarMenuItem(title: "Heading 2", action: { applyStyle(style: .h2) }),
                            ToolbarMenuItem(title: "Heading 3", action: { applyStyle(style: .h3) }),
                            ToolbarMenuItem(title: "Body", action: { applyStyle(style: .body) })
                        ])
                    }
                    
                    toolbarGroup {
                        toolbarButton(icon: "bold", action: { toggleBold() })
                        toolbarButton(icon: "italic", action: { toggleItalic() })
                        toolbarButton(icon: "underline", action: { toggleUnderline() })
                    }
                    
                    toolbarGroup {
                        toolbarButtonWithMenu(icon: "text.alignleft", menuItems: [
                            ToolbarMenuItem(title: "Left", icon: "text.alignleft", action: { setAlignment(.leading) }),
                            ToolbarMenuItem(title: "Center", icon: "text.aligncenter", action: { setAlignment(.center) }),
                            ToolbarMenuItem(title: "Right", icon: "text.alignright", action: { setAlignment(.trailing) })
                        ])
                    }
                    
                    toolbarGroup {
                        toolbarButtonWithMenu(icon: "list.bullet", menuItems: [
                            ToolbarMenuItem(title: "Bullet List", icon: "list.bullet", action: { toggleBulletList() }),
                            ToolbarMenuItem(title: "Numbered List", icon: "list.number", action: { toggleNumberedList() })
                        ])
                    }
                    
                    toolbarGroup {
                        toolbarButton(icon: "arrow.uturn.backward", isEnabled: historyIndex > 0, action: { undo() })
                        toolbarButton(icon: "arrow.uturn.forward", isEnabled: historyIndex < editHistory.count - 1, action: { redo() })
                    }
                    
                    toolbarGroup {
                        toolbarButton(icon: "photo", action: { insertImage() })
                        toolbarButton(icon: "link", action: { insertLink() })
                        toolbarButton(icon: "doc", action: { insertFile() })
                    }
                }
                .padding(.horizontal, 8)
                .padding(.vertical, 6)
            }
        }
        .background(cardBackgroundColor)
        .overlay(Rectangle().frame(height: 1).foregroundColor(borderColor), alignment: .bottom)
    }
    
    private func toolbarGroup<Content: View>(@ViewBuilder content: () -> Content) -> some View {
        HStack(spacing: 2) {
            content()
        }
        .padding(.horizontal, 2)
        .padding(.vertical, 2)
        .background(Color.primary.opacity(0.03))
        .cornerRadius(6)
    }

    private func toolbarButton(icon: String, isEnabled: Bool = true, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: icon)
                .font(.system(size: 14))
                .foregroundColor(isEnabled ? Color.primary.opacity(0.8) : Color.primary.opacity(0.3))
                .frame(width: 28, height: 28)
                .contentShape(Rectangle())
        }
        .disabled(!isEnabled)
        .buttonStyle(ToolbarButtonStyle())
    }

    private func toolbarButtonWithMenu(icon: String, menuItems: [ToolbarMenuItem]) -> some View {
        Menu {
            ForEach(menuItems) { item in
                Button(action: item.action) {
                    if let iconName = item.icon {
                        Label(item.title, systemImage: iconName)
                    } else {
                        Text(item.title)
                    }
                }
            }
        } label: {
            Image(systemName: icon)
                .font(.system(size: 14))
                .foregroundColor(Color.primary.opacity(0.8))
                .frame(width: 28, height: 28)
                .contentShape(Rectangle())
        }
        .buttonStyle(ToolbarButtonStyle())
    }

    // MARK: Editor Area
    private var editorArea: some View {
        ZStack(alignment: .topLeading) {
            // Background
            cardBackgroundColor
            
            // Text editor
            TextEditor(text: $documentText)
                .font(.body)
                .lineSpacing(1.2)
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .frame(minHeight: 300)
                .background(Color.clear)
                .focused($focusSection, equals: .editor)
                .onChange(of: documentText) { newValue in
                    addHistoryEntry(newValue, .replace)
                    scheduleSave()
                    updateCursorPosition()
                }
                .overlay(
                    // Placeholder when document is empty
                    Group {
                        if documentText.isEmpty && focusSection != .editor {
                            Text("Start typing...")
                                .foregroundColor(subtleTextColor)
                                .padding(.horizontal, 20)
                                .padding(.top, 18)
                                .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
                                .onTapGesture {
                                    focusSection = .editor
                                }
                        }
                    }
                )
                .overlay(
                    // Show collaborator cursors
                    ForEach(activeCollaborators.filter { $0.cursorPosition != nil }, id: \.id) { collaborator in
                        if let position = collaborator.cursorPosition, position <= documentText.count {
                            collaboratorCursorIndicator(for: collaborator, at: position)
                        }
                    }
                )
        }
        .cornerRadius(cornerRadius)
        .shadow(color: Color.black.opacity(0.05), radius: 2, x: 0, y: 1)
        .padding(.horizontal, 16)
        .padding(.top, 8)
    }
    
    // MARK: Collaborator Cursors
    private var collaboratorCursors: some View {
        HStack(spacing: 2) {
            ForEach(activeCollaborators.filter { $0.id != localUser && $0.isActive }, id: \.id) { collaborator in
                HStack(spacing: 3) {
                    ZStack {
                        Circle()
                            .fill(collaborator.color)
                            .frame(width: 8, height: 8)
                    }
                    Text(collaborator.name.components(separatedBy: " ").first ?? "")
                        .font(.caption2)
                        .foregroundColor(collaborator.color)
                        .fontWeight(.medium)
                }
                .padding(.horizontal, 6)
                .padding(.vertical, 2)
                .background(collaborator.color.opacity(0.1))
                .cornerRadius(4)
            }
        }
        .padding(.horizontal, 16)
        .opacity(activeCollaborators.filter { $0.id != localUser && $0.isActive }.isEmpty ? 0 : 1)
        .animation(.easeInOut, value: activeCollaborators)
    }
    
    // Individual cursor indicator
    private func collaboratorCursorIndicator(for collaborator: Collaborator, at position: Int) -> some View {
        // This is a placeholder since TextEditor doesn't provide cursor position
        // In a real implementation, you would calculate the actual position using NSRange or UITextView methods
        Color.clear // Just a placeholder
    }

    // MARK: Collaboration Status Indicators
    private var collaborationStatusIndicators: some View {
        HStack(spacing: 12) {
            if !activeCollaborators.isEmpty {
                HStack(spacing: 4) { 
                    Image(systemName: "eye")
                        .font(.caption2)
                        .foregroundColor(subtleTextColor)
                    
                    Text("\(activeCollaborators.count) viewing")
                        .font(.caption)
                        .foregroundColor(subtleTextColor)
                }
                .padding(.horizontal, 8)
                .padding(.vertical, 4)
                .background(cardBackgroundColor)
                .cornerRadius(4)
                .overlay(
                    RoundedRectangle(cornerRadius: 4)
                        .stroke(borderColor, lineWidth: 1)
                )
            }
        }
        .padding(.horizontal, 16)
        .animation(.easeInOut, value: activeCollaborators.count)
    }

    // MARK: Collaborators View
    private var collaboratorsView: some View {
        VStack(spacing: 0) {
            // Header
            HStack { 
                Text("Collaborators")
                    .font(.headline)
                    .fontWeight(.semibold)
                
                Spacer()
                
                Button(action: { showCollaboratorsList = false }) { 
                    Image(systemName: "xmark")
                        .font(.subheadline)
                        .foregroundColor(subtleTextColor)
                } 
            }
            .padding(.horizontal, 16)
            .padding(.top, 16)
            .padding(.bottom, 8)
            
            Divider()
                .padding(.horizontal, 16)
                .padding(.bottom, 8)
            
            if activeCollaborators.isEmpty {
                VStack(spacing: 16) { 
                    Image(systemName: "person.2")
                        .font(.system(size: 48))
                        .foregroundColor(subtleTextColor.opacity(0.5))
                        .padding(.top, 40)
                    
                    Text("No one else is viewing this document")
                        .font(.headline)
                        .foregroundColor(subtleTextColor)
                    
                    Text("Invite others to collaborate in real-time")
                        .font(.subheadline)
                        .foregroundColor(subtleTextColor)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 32)
                }
                .frame(maxHeight: .infinity)
            } else {
                // Active collaborators section
                if activeCollaborators.filter({ $0.isActive }).count > 0 {
                    collaboratorListSection(
                        title: "Active now",
                        collaborators: activeCollaborators.filter { $0.isActive }
                    )
                }
                
                // Inactive collaborators section
                if activeCollaborators.filter({ !$0.isActive }).count > 0 {
                    collaboratorListSection(
                        title: "Invited",
                        collaborators: activeCollaborators.filter { !$0.isActive }
                    )
                }
                
                Spacer()
            }
            
            // Invite button
            if permissionLevel == "owner" || permissionLevel == "admin" {
                Button(action: { inviteCollaborators() }) { 
                    HStack { 
                        Image(systemName: "person.badge.plus")
                        Text("Invite Collaborators") 
                    }
                    .font(.body)
                    .fontWeight(.medium)
                    .foregroundColor(.white)
                    .padding()
                    .frame(maxWidth: .infinity)
                    .background(Color.blue)
                    .cornerRadius(cornerRadius)
                    .padding(.horizontal, 16)
                    .padding(.vertical, 16)
                }
            }
        }
    }
    
    // Collaborator list section with title
    private func collaboratorListSection(title: String, collaborators: [Collaborator]) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text(title)
                .font(.caption)
                .fontWeight(.medium)
                .foregroundColor(subtleTextColor)
                .padding(.horizontal, 16)
                .padding(.vertical, 6)
            
            ForEach(collaborators) { collaborator in
                collaboratorListItem(collaborator)
            }
        }
    }
    
    // Individual collaborator list item
    private func collaboratorListItem(_ collaborator: Collaborator) -> some View {
        HStack(spacing: 12) {
            // Avatar
            ZStack { 
                Circle()
                    .fill(collaborator.color)
                    .frame(width: 36, height: 36)
                
                Text(collaborator.initials)
                    .font(.subheadline)
                    .fontWeight(.medium)
                    .foregroundColor(.white) 
            }
            
            // Info
            VStack(alignment: .leading, spacing: 2) {
                Text(collaborator.name)
                    .font(.body)
                
                HStack(spacing: 4) { 
                    Circle()
                        .fill(collaborator.isActive ? Color.green : Color.gray)
                        .frame(width: 6, height: 6)
                    
                    Text(collaborator.isActive ? "Active now" : "Idle")
                        .font(.caption)
                        .foregroundColor(subtleTextColor) 
                }
            }
            
            Spacer()
            
            // Permission level badge
            Text(collaborator.permissionLevel)
                .font(.caption)
                .foregroundColor(subtleTextColor)
                .padding(.horizontal, 6)
                .padding(.vertical, 2)
                .background(Color.primary.opacity(0.05))
                .cornerRadius(4)
        }
        .padding(.vertical, 8)
        .padding(.horizontal, 16)
        .contentShape(Rectangle())
        .contextMenu {
            if permissionLevel == "owner" || permissionLevel == "admin" {
                Button(action: {
                    // Change to viewer
                }) {
                    Label("Make Viewer", systemImage: "eye")
                }
                
                Button(action: {
                    // Change to editor
                }) {
                    Label("Make Editor", systemImage: "pencil")
                }
                
                Divider()
                
                Button(role: .destructive, action: {
                    // Remove collaborator
                }) {
                    Label("Remove", systemImage: "person.fill.xmark")
                }
            }
        }
    }

    // MARK: API and Socket Functions
    private func loadDocument() {
        // Simulate loading document for now
        // Replace with actual API call using FeedViewModel
        documentTitle = "Team Discussion Document"
        documentText = "This is a collaborative document for team discussion. Edit me!"
        
        // Setup default collaborators
        let colors: [Color] = [.blue, .green, .purple, .orange, .red, .pink]
        activeCollaborators = [
            Collaborator(id: "user1", name: "Jane Smith", isActive: true, color: colors[0]),
            Collaborator(id: "user2", name: "Alex Johnson", isActive: false, color: colors[1])
        ]
        
        connectionStatus = .connecting
        
        // Simulating successful load with a delay
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.5) {
            connectionStatus = .connected
            
            // Initialize history
            if editHistory.isEmpty {
                editHistory = [DocumentEdit(text: documentText, timestamp: Date(), operation: .none)]
            }
            
            // Update focus to editor
            self.focusSection = .editor
        }
    }
    
    private func connectToSocket() {
        guard let url = URL(string: "wss://yourdomain.com/ws/document/\(documentId ?? "new")") else { return }
        
        let session = URLSession(configuration: .default)
        socketTask = session.webSocketTask(with: url)
        socketTask?.resume()
        
        receiveMessage()
        
        // Setup ping timer to keep connection alive
        pingTimer = Timer.scheduledTimer(withTimeInterval: heartbeatInterval, repeats: true) { _ in
            self.pingSocket()
        }
        
        // Send initial presence message
        sendPresence()
    }
    
    private func receiveMessage() {
        socketTask?.receive { result in
            // Using a non-weak capture here because DocumentView is a struct (not a class)
            // and we need to avoid the 'weak' error
            switch result {
            case .success(let message):
                switch message {
                case .string(let text):
                    self.handleSocketMessage(text)
                case .data(let data):
                    // Handle binary data if needed
                    break
                @unknown default:
                    break
                }
                
                // Continue receiving messages
                self.receiveMessage()
                
            case .failure(let error):
                print("WebSocket receive error: \(error)")
                self.connectionStatus = .disconnected
                
                // Try to reconnect after delay
                DispatchQueue.main.asyncAfter(deadline: .now() + 5) {
                    self.connectToSocket()
                }
            }
        }
    }
    
    private func handleSocketMessage(_ messageText: String) {
        guard let data = messageText.data(using: .utf8) else { return }
        
        do {
            let json = try JSONSerialization.jsonObject(with: data, options: []) as? [String: Any]
            
            if let type = json?["type"] as? String {
                switch type {
                case "presence":
                    handlePresenceUpdate(json)
                case "content_update":
                    handleContentUpdate(json)
                case "cursor_update":
                    handleCursorUpdate(json)
                case "title_update":
                    handleTitleUpdate(json)
                default:
                    break
                }
            }
        } catch {
            print("Error parsing socket message: \(error)")
        }
    }
    
    private func handlePresenceUpdate(_ data: [String: Any]?) {
        guard let users = data?["users"] as? [[String: Any]] else { return }
        
        DispatchQueue.main.async {
            // Update active collaborators based on presence data
            self.activeCollaborators = users.compactMap { userData -> Collaborator? in
                guard let id = userData["id"] as? String,
                      let name = userData["name"] as? String,
                      let isActive = userData["isActive"] as? Bool,
                      let colorHex = userData["color"] as? String,
                      let permissionLevel = userData["permissionLevel"] as? String else {
                    return nil
                }
                
                let color = Color(hex: colorHex) ?? .blue
                return Collaborator(id: id, name: name, isActive: isActive, color: color, permissionLevel: permissionLevel)
            }
        }
    }
    
    private func handleContentUpdate(_ data: [String: Any]?) {
        guard let userId = data?["userId"] as? String,
              let content = data?["content"] as? String,
              userId != localUser else { return }
        
        DispatchQueue.main.async {
            self.documentText = content
            
            // Add to history without triggering a save
            self.editHistory.append(DocumentEdit(text: content, timestamp: Date(), operation: .replace))
            self.historyIndex = self.editHistory.count - 1
        }
    }
    
    private func handleCursorUpdate(_ data: [String: Any]?) {
        guard let userId = data?["userId"] as? String,
              let position = data?["position"] as? Int else { return }
        
        DispatchQueue.main.async {
            if let index = self.activeCollaborators.firstIndex(where: { $0.id == userId }) {
                var collaborator = self.activeCollaborators[index]
                // Update collaborator with new cursor position
                self.activeCollaborators[index] = Collaborator(
                    id: collaborator.id,
                    name: collaborator.name,
                    isActive: true,
                    color: collaborator.color,
                    permissionLevel: collaborator.permissionLevel,
                    cursorPosition: position
                )
            }
        }
    }
    
    private func handleTitleUpdate(_ data: [String: Any]?) {
        guard let userId = data?["userId"] as? String,
              let title = data?["title"] as? String,
              userId != localUser else { return }
        
        DispatchQueue.main.async {
            self.documentTitle = title
        }
    }
    
    private func pingSocket() {
        socketTask?.sendPing { error in
            if let error = error {
                print("Error pinging WebSocket: \(error)")
                self.connectionStatus = .disconnected
            }
        }
    }
    
    private func sendPresence() {
        let message: [String: Any] = [
            "type": "presence",
            "userId": localUser,
            "name": localUserName,
            "color": localUserColor.toHex() ?? "#0000FF"
        ]
        
        guard let data = try? JSONSerialization.data(withJSONObject: message),
              let messageText = String(data: data, encoding: .utf8) else { return }
        
        socketTask?.send(.string(messageText)) { error in
            if let error = error {
                print("Error sending presence update: \(error)")
            }
        }
    }
    
    private func sendContentUpdate() {
        let message: [String: Any] = [
            "type": "content_update",
            "userId": localUser,
            "content": documentText
        ]
        
        guard let data = try? JSONSerialization.data(withJSONObject: message),
              let messageText = String(data: data, encoding: .utf8) else { return }
        
        socketTask?.send(.string(messageText)) { error in
            if let error = error {
                print("Error sending content update: \(error)")
            }
        }
    }
    
    private func updateCursorPosition() {
        // Get cursor position from NSRange
        // This is a placeholder since TextEditor doesn't expose selection
        let cursorPosition = documentText.count
        
        let message: [String: Any] = [
            "type": "cursor_update",
            "userId": localUser,
            "position": cursorPosition
        ]
        
        guard let data = try? JSONSerialization.data(withJSONObject: message),
              let messageText = String(data: data, encoding: .utf8) else { return }
        
        socketTask?.send(.string(messageText)) { error in
            if let error = error {
                print("Error sending cursor update: \(error)")
            }
        }
    }
    
    private func sendTitleUpdate() {
        let message: [String: Any] = [
            "type": "title_update",
            "userId": localUser,
            "title": documentTitle
        ]
        
        guard let data = try? JSONSerialization.data(withJSONObject: message),
              let messageText = String(data: data, encoding: .utf8) else { return }
        
        socketTask?.send(.string(messageText)) { error in
            if let error = error {
                print("Error sending title update: \(error)")
            }
        }
        
        // Also save the title to backend
        saveDocument()
    }
    
    private func disconnectSocket() {
        socketTask?.cancel(with: .goingAway, reason: nil)
        socketTask = nil
        pingTimer?.invalidate()
        pingTimer = nil
    }
    
    // MARK: Document Operation Functions
    private func toggleBold() {
        // Implement text formatting
    }
    
    private func toggleItalic() {
        // Implement text formatting
    }
    
    private func toggleUnderline() {
        // Implement text formatting
    }
    
    private func setAlignment(_ alignment: TextAlignment) {
        // Implement text alignment
    }
    
    private func toggleBulletList() {
        // Implement bullet list
    }
    
    private func toggleNumberedList() {
        // Implement numbered list
    }
    
    private func applyStyle(style: TextStyle) {
        // Implement text style
    }
    
    private func insertImage() {
        // Implement image insertion
    }
    
    private func insertLink() {
        // Implement link insertion
    }
    
    private func insertFile() {
        // Implement file insertion
    }
    
    private func addHistoryEntry(_ text: String, _ operation: DocumentOperation) {
        if historyIndex < editHistory.count - 1 { 
            editHistory.removeSubrange((historyIndex+1)...) 
        }
        
        editHistory.append(DocumentEdit(text: text, timestamp: Date(), operation: operation))
        historyIndex = editHistory.count - 1
    }
    
    private func undo() { 
        guard historyIndex > 0 else { return }
        historyIndex -= 1
        documentText = editHistory[historyIndex].text
        sendContentUpdate()
    }
    
    private func redo() { 
        guard historyIndex < editHistory.count - 1 else { return }
        historyIndex += 1
        documentText = editHistory[historyIndex].text
        sendContentUpdate()
    }

    private var saveTimer: Timer?
    private func scheduleSave() {
        saveTimer?.invalidate()
        saveTimer = Timer.scheduledTimer(withTimeInterval: 2.0, repeats: false) { _ in 
            saveDocument() 
        }
    }
    
    private func saveDocument() {
        isSaving = true
        showSaveIndicator = true
        
        // Tell collaborators about the change
        sendContentUpdate()
        
        // Save to backend
        // In a real implementation, this would call viewModel API methods
        Task { @MainActor in
            do {
                try await Task.sleep(nanoseconds: 1_500_000_000)
                lastSaved = Date()
                isSaving = false
                
                // Hide save indicator after a delay
                DispatchQueue.main.asyncAfter(deadline: .now() + 2) {
                    withAnimation {
                        showSaveIndicator = false
                    }
                }
            } catch {
                print("Error saving document: \(error)")
                
                // Using Task-local @MainActor properties directly
                lastSaved = Date()
                isSaving = false
                
                // Hide save indicator
                DispatchQueue.main.asyncAfter(deadline: .now() + 2) {
                    withAnimation {
                        showSaveIndicator = false
                    }
                }
            }
        }
    }
    
    private func shareDocument() {
        // Generate share link
        // In a real implementation, this would call viewModel API methods
        let shareLink = "https://yourdomain.com/documents/\(documentId ?? "new")"
        
        // Share the link
        let activityVC = UIActivityViewController(
            activityItems: [shareLink],
            applicationActivities: nil
        )
        
        if let window = UIApplication.shared.windows.first,
           let rootVC = window.rootViewController {
            rootVC.present(activityVC, animated: true)
        }
    }
    
    private func inviteCollaborators() {
        // Implement invite flow
    }
    
    // MARK: Utilities
    private func timeAgo(from date: Date) -> String {
        let now = Date()
        let components = Calendar.current.dateComponents([.minute, .hour, .day], from: date, to: now)
        
        if let day = components.day, day > 0 {
            return "\(day) day\(day == 1 ? "" : "s") ago"
        } else if let hour = components.hour, hour > 0 {
            return "\(hour) hour\(hour == 1 ? "" : "s") ago"
        } else if let minute = components.minute, minute > 0 {
            return "\(minute) minute\(minute == 1 ? "" : "s") ago"
        } else {
            return "just now"
        }
    }
}

// MARK: - File Import Handler
extension DocumentView {
    func handleFileImport(result: Result<[URL], Error>) {
        do {
            let urls = try result.get()
            for url in urls {
                guard url.startAccessingSecurityScopedResource() else { continue }
                defer { url.stopAccessingSecurityScopedResource() }
                let data = try Data(contentsOf: url)
                let name = url.lastPathComponent
                let tempURL = FileManager.default.temporaryDirectory.appendingPathComponent(name)
                try data.write(to: tempURL)
                let elem = CollabElement(from: .file(tempURL, name))
                DispatchQueue.main.async { elements.append(elem) }
            }
        } catch {
            print("Error importing files: \(error)")
        }
    }
}

// MARK: - Resize Handles Support
extension DocumentView {
    enum ResizeHandlePosition: CaseIterable { case topLeft, topRight, bottomLeft, bottomRight }
    private func position(for handle: ResizeHandlePosition, in element: CollabElement) -> CGPoint {
        let w = element.size.width, h = element.size.height, c = element.position
        switch handle {
        case .topLeft: return CGPoint(x: c.x - w/2, y: c.y - h/2)
        case .topRight: return CGPoint(x: c.x + w/2, y: c.y - h/2)
        case .bottomLeft: return CGPoint(x: c.x - w/2, y: c.y + h/2)
        case .bottomRight: return CGPoint(x: c.x + w/2, y: c.y + h/2) }
    }
}

// MARK: - Color Extensions for Collaboration
extension Color {
    func toHex() -> String? {
        let uiColor = UIColor(self)
        guard let components = uiColor.cgColor.components, components.count >= 3 else {
            return nil
        }
        
        let r = Float(components[0])
        let g = Float(components[1])
        let b = Float(components[2])
        
        return String(format: "#%02lX%02lX%02lX", 
                     lroundf(r * 255), 
                     lroundf(g * 255), 
                     lroundf(b * 255))
    }
    
    init?(hex: String) {
        let hex = hex.trimmingCharacters(in: CharacterSet.alphanumerics.inverted)
        var int: UInt64 = 0
        
        guard Scanner(string: hex).scanHexInt64(&int) else { return nil }
        
        let a, r, g, b: UInt64
        switch hex.count {
        case 3: // RGB (12-bit)
            (a, r, g, b) = (255, (int >> 8) * 17, (int >> 4 & 0xF) * 17, (int & 0xF) * 17)
        case 6: // RGB (24-bit)
            (a, r, g, b) = (255, int >> 16, int >> 8 & 0xFF, int & 0xFF)
        case 8: // ARGB (32-bit)
            (a, r, g, b) = (int >> 24, int >> 16 & 0xFF, int >> 8 & 0xFF, int & 0xFF)
        default:
            return nil
        }
        
        self.init(
            .sRGB,
            red: Double(r) / 255,
            green: Double(g) / 255,
            blue: Double(b) / 255,
            opacity: Double(a) / 255
        )
    }
}

// MARK: - Custom Button Style
struct ToolbarButtonStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .background(configuration.isPressed ? Color.primary.opacity(0.1) : Color.clear)
            .cornerRadius(4)
            .contentShape(Rectangle())
    }
}

#Preview {
    DocumentView()
}