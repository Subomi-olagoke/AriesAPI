import SwiftUI

struct DocumentListView: View {
    // MARK: - State Variables
    @State private var documents: [DocumentPreview] = []
    @State private var isLoading: Bool = true
    @State private var searchText: String = ""
    @State private var showCreateSheet: Bool = false
    @State private var selectedSortOption: SortOption = .lastModified
    @State private var selectedFilterOption: FilterOption = .all
    
    // MARK: - Environment
    @Environment(\.colorScheme) private var colorScheme
    
    // MARK: - Computed Properties
    private var backgroundColor: Color {
        colorScheme == .dark ? Color(white: 0.1) : Color(white: 0.98)
    }
    
    private var filteredDocuments: [DocumentPreview] {
        var result = documents
        
        // Apply search
        if !searchText.isEmpty {
            result = result.filter { $0.title.localizedCaseInsensitiveContains(searchText) }
        }
        
        // Apply filter
        switch selectedFilterOption {
        case .all:
            break // Show all documents
        case .shared:
            result = result.filter { $0.isShared }
        case .owned:
            result = result.filter { $0.isOwner }
        case .recent:
            let calendar = Calendar.current
            let oneWeekAgo = calendar.date(byAdding: .day, value: -7, to: Date()) ?? Date()
            result = result.filter { $0.lastModified > oneWeekAgo }
        }
        
        // Apply sort
        switch selectedSortOption {
        case .lastModified:
            result.sort { $0.lastModified > $1.lastModified }
        case .title:
            result.sort { $0.title < $1.title }
        case .created:
            result.sort { $0.dateCreated > $1.dateCreated }
        }
        
        return result
    }
    
    // MARK: - Body
    var body: some View {
        NavigationStack {
            ZStack {
                backgroundColor.ignoresSafeArea()
                
                VStack(spacing: 0) {
                    // Search and filter bar
                    searchAndFilterBar
                    
                    // Documents list
                    if isLoading {
                        loadingView
                    } else if filteredDocuments.isEmpty {
                        emptyStateView
                    } else {
                        documentsList
                    }
                }
            }
            .navigationTitle("Documents")
            .navigationBarTitleDisplayMode(.large)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button(action: {
                        showCreateSheet = true
                    }) {
                        Image(systemName: "plus")
                            .font(.headline)
                    }
                }
                
                ToolbarItem(placement: .topBarTrailing) {
                    Menu {
                        Section("Sort by") {
                            ForEach(SortOption.allCases, id: \.self) { option in
                                Button {
                                    selectedSortOption = option
                                } label: {
                                    Label(option.title, systemImage: selectedSortOption == option ? "checkmark" : "")
                                }
                            }
                        }
                        
                        Section("Filter") {
                            ForEach(FilterOption.allCases, id: \.self) { option in
                                Button {
                                    selectedFilterOption = option
                                } label: {
                                    Label(option.title, systemImage: selectedFilterOption == option ? "checkmark" : "")
                                }
                            }
                        }
                    } label: {
                        Image(systemName: "ellipsis.circle")
                            .font(.headline)
                    }
                }
            }
            .sheet(isPresented: $showCreateSheet) {
                newDocumentSheet
            }
            .onAppear {
                loadDocuments()
            }
        }
    }
    
    // MARK: - Search and Filter Bar
    private var searchAndFilterBar: some View {
        VStack(spacing: 0) {
            // Search field
            HStack {
                Image(systemName: "magnifyingglass")
                    .foregroundColor(.gray)
                
                TextField("Search documents", text: $searchText)
                    .font(.body)
                
                if !searchText.isEmpty {
                    Button(action: {
                        searchText = ""
                    }) {
                        Image(systemName: "xmark.circle.fill")
                            .foregroundColor(.gray)
                    }
                }
            }
            .padding(.horizontal)
            .padding(.vertical, 8)
            .background(colorScheme == .dark ? Color(white: 0.2) : Color.white)
            .cornerRadius(10)
            .padding(.horizontal)
            .padding(.vertical, 8)
            
            // Filter chips
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(FilterOption.allCases, id: \.self) { option in
                        Button(action: {
                            selectedFilterOption = option
                        }) {
                            Text(option.title)
                                .font(.subheadline)
                                .padding(.horizontal, 12)
                                .padding(.vertical, 6)
                                .background(
                                    selectedFilterOption == option ?
                                    Color.primary.opacity(0.1) :
                                    Color.gray.opacity(0.1)
                                )
                                .cornerRadius(16)
                                .overlay(
                                    RoundedRectangle(cornerRadius: 16)
                                        .stroke(
                                            selectedFilterOption == option ?
                                            Color.primary.opacity(0.3) :
                                            Color.clear,
                                            lineWidth: 1
                                        )
                                )
                                .foregroundColor(
                                    selectedFilterOption == option ?
                                    Color.primary :
                                    Color.gray
                                )
                        }
                    }
                }
                .padding(.horizontal)
                .padding(.bottom, 8)
            }
            
            Divider()
        }
        .background(colorScheme == .dark ? Color.black : Color.white)
    }
    
    // MARK: - Documents List
    private var documentsList: some View {
        List {
            ForEach(filteredDocuments) { document in
                NavigationLink(destination: DocumentView()) {
                    DocumentRow(document: document)
                }
                .listRowBackground(colorScheme == .dark ? Color(white: 0.15) : Color.white)
                .listRowSeparator(.hidden)
                .listRowInsets(EdgeInsets(top: 4, leading: 16, bottom: 4, trailing: 16))
            }
            .onDelete(perform: deleteDocuments)
        }
        .listStyle(.plain)
        .background(backgroundColor)
    }
    
    // MARK: - Document Row
    struct DocumentRow: View {
        let document: DocumentPreview
        @Environment(\.colorScheme) private var colorScheme
        
        var body: some View {
            HStack(spacing: 16) {
                // Document icon
                ZStack {
                    RoundedRectangle(cornerRadius: 8)
                        .fill(colorScheme == .dark ? Color(white: 0.2) : Color(white: 0.95))
                        .frame(width: 40, height: 50)
                    
                    Image(systemName: "doc.text")
                        .font(.title3)
                        .foregroundColor(Color.primary.opacity(0.7))
                }
                
                // Document info
                VStack(alignment: .leading, spacing: 4) {
                    Text(document.title)
                        .font(.headline)
                        .lineLimit(1)
                    
                    HStack(spacing: 8) {
                        // Last modified date
                        Text(document.lastModified, style: .relative)
                            .font(.caption)
                            .foregroundColor(.gray)
                        
                        // Shared indicator
                        if document.isShared {
                            HStack(spacing: 2) {
                                Image(systemName: "person.2")
                                    .font(.caption2)
                                
                                Text("Shared")
                                    .font(.caption2)
                            }
                            .foregroundColor(.gray)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 2)
                            .background(Color.gray.opacity(0.1))
                            .cornerRadius(4)
                        }
                    }
                }
                
                Spacer()
                
                // Collaborator avatars if shared
                if document.isShared && !document.collaborators.isEmpty {
                    HStack(spacing: -8) {
                        ForEach(document.collaborators.prefix(2), id: \.self) { color in
                            Circle()
                                .fill(color)
                                .frame(width: 24, height: 24)
                                .overlay(
                                    Circle()
                                        .stroke(colorScheme == .dark ? Color(white: 0.15) : Color.white, lineWidth: 1)
                                )
                        }
                        
                        if document.collaborators.count > 2 {
                            Circle()
                                .fill(Color.gray.opacity(0.3))
                                .frame(width: 24, height: 24)
                                .overlay(
                                    Text("+\(document.collaborators.count - 2)")
                                        .font(.caption2)
                                        .foregroundColor(Color.primary)
                                )
                                .overlay(
                                    Circle()
                                        .stroke(colorScheme == .dark ? Color(white: 0.15) : Color.white, lineWidth: 1)
                                )
                        }
                    }
                }
            }
            .padding(.vertical, 8)
        }
    }
    
    // MARK: - Loading View
    private var loadingView: some View {
        VStack(spacing: 20) {
            ProgressView()
                .scaleEffect(1.5)
            
            Text("Loading documents...")
                .font(.callout)
                .foregroundColor(.gray)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(backgroundColor)
    }
    
    // MARK: - Empty State View
    private var emptyStateView: some View {
        VStack(spacing: 20) {
            Image(systemName: "doc.text.magnifyingglass")
                .font(.system(size: 60))
                .foregroundColor(.gray)
            
            Text(searchText.isEmpty ? "No documents found" : "No matching documents")
                .font(.headline)
            
            Text(searchText.isEmpty ? 
                 "Create a new document to get started" : 
                 "Try changing your search or filters")
                .font(.callout)
                .foregroundColor(.gray)
                .multilineTextAlignment(.center)
                .frame(maxWidth: 250)
            
            if searchText.isEmpty {
                Button(action: {
                    showCreateSheet = true
                }) {
                    Text("Create Document")
                        .font(.headline)
                        .foregroundColor(.white)
                        .padding(.horizontal, 20)
                        .padding(.vertical, 10)
                        .background(Color.primary)
                        .cornerRadius(8)
                }
                .padding(.top, 10)
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(backgroundColor)
    }
    
    // MARK: - New Document Sheet
    private var newDocumentSheet: some View {
        NavigationStack {
            VStack(spacing: 24) {
                // Template selection
                VStack(alignment: .leading, spacing: 8) {
                    Text("Select a template")
                        .font(.headline)
                    
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 16) {
                            // Blank document
                            templateCard(
                                icon: "doc",
                                title: "Blank",
                                description: "Start with an empty document",
                                isSelected: true
                            )
                            
                            // Other templates
                            templateCard(
                                icon: "doc.text",
                                title: "Proposal",
                                description: "Document template with proposal sections"
                            )
                            
                            templateCard(
                                icon: "list.bullet",
                                title: "Meeting notes",
                                description: "Template for tracking meeting discussions"
                            )
                        }
                        .padding(.horizontal)
                    }
                }
                .padding(.horizontal)
                
                // Divider
                Divider()
                    .padding(.horizontal)
                
                // Document title input
                VStack(alignment: .leading, spacing: 8) {
                    Text("Document title")
                        .font(.headline)
                    
                    TextField("Untitled Document", text: .constant(""))
                        .font(.body)
                        .padding()
                        .background(Color.gray.opacity(0.1))
                        .cornerRadius(8)
                }
                .padding(.horizontal)
                
                Spacer()
            }
            .padding(.top, 16)
            .navigationTitle("New Document")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") {
                        showCreateSheet = false
                    }
                }
                
                ToolbarItem(placement: .confirmationAction) {
                    Button("Create") {
                        createNewDocument()
                        showCreateSheet = false
                    }
                    .fontWeight(.semibold)
                }
            }
        }
        .presentationDetents([.medium, .large])
    }
    
    private func templateCard(icon: String, title: String, description: String, isSelected: Bool = false) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            // Icon
            Image(systemName: icon)
                .font(.title)
                .foregroundColor(isSelected ? Color.primary : Color.gray)
            
            // Title and description
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.headline)
                
                Text(description)
                    .font(.caption)
                    .foregroundColor(.gray)
                    .lineLimit(2)
            }
            
            Spacer()
        }
        .frame(width: 160, height: 140)
        .padding()
        .background(
            RoundedRectangle(cornerRadius: 12)
                .fill(colorScheme == .dark ? Color(white: 0.2) : Color.white)
        )
        .overlay(
            RoundedRectangle(cornerRadius: 12)
                .stroke(isSelected ? Color.primary : Color.gray.opacity(0.3), lineWidth: isSelected ? 2 : 1)
        )
    }
    
    // MARK: - Functions
    
    private func loadDocuments() {
        // Simulate loading from API
        isLoading = true
        
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.5) {
            // Sample data
            self.documents = [
                DocumentPreview(
                    id: "1",
                    title: "Project Proposal",
                    lastModified: Date().addingTimeInterval(-3600 * 5),
                    dateCreated: Date().addingTimeInterval(-3600 * 24 * 7),
                    isShared: true,
                    isOwner: true,
                    collaborators: [.blue, .green]
                ),
                DocumentPreview(
                    id: "2",
                    title: "Meeting Notes",
                    lastModified: Date().addingTimeInterval(-3600 * 24),
                    dateCreated: Date().addingTimeInterval(-3600 * 24 * 14),
                    isShared: true,
                    isOwner: true,
                    collaborators: [.purple]
                ),
                DocumentPreview(
                    id: "3",
                    title: "Research Document",
                    lastModified: Date().addingTimeInterval(-3600 * 2),
                    dateCreated: Date().addingTimeInterval(-3600 * 24 * 2),
                    isShared: false,
                    isOwner: true,
                    collaborators: []
                ),
                DocumentPreview(
                    id: "4",
                    title: "Product Roadmap",
                    lastModified: Date().addingTimeInterval(-3600 * 48),
                    dateCreated: Date().addingTimeInterval(-3600 * 24 * 30),
                    isShared: true,
                    isOwner: false,
                    collaborators: [.orange, .red, .blue]
                )
            ]
            
            self.isLoading = false
        }
    }
    
    private func deleteDocuments(at offsets: IndexSet) {
        // Get the documents to delete
        let documentsToDelete = offsets.map { filteredDocuments[$0] }
        
        // Remove from the main list
        for document in documentsToDelete {
            if let index = documents.firstIndex(where: { $0.id == document.id }) {
                documents.remove(at: index)
            }
        }
        
        // In a real app, call API to delete documents
    }
    
    private func createNewDocument() {
        // In a real app, this would create a document and navigate to it
        let newDocument = DocumentPreview(
            id: UUID().uuidString,
            title: "Untitled Document",
            lastModified: Date(),
            dateCreated: Date(),
            isShared: false,
            isOwner: true,
            collaborators: []
        )
        
        documents.append(newDocument)
    }
}

// MARK: - Supporting Types

// Document preview model
struct DocumentPreview: Identifiable {
    let id: String
    let title: String
    let lastModified: Date
    let dateCreated: Date
    let isShared: Bool
    let isOwner: Bool
    let collaborators: [Color]
}

// Sort options
enum SortOption: CaseIterable {
    case lastModified
    case title
    case created
    
    var title: String {
        switch self {
        case .lastModified: return "Last modified"
        case .title: return "Title"
        case .created: return "Date created"
        }
    }
}

// Filter options
enum FilterOption: CaseIterable {
    case all
    case shared
    case owned
    case recent
    
    var title: String {
        switch self {
        case .all: return "All Documents"
        case .shared: return "Shared"
        case .owned: return "Owned by me"
        case .recent: return "Recent"
        }
    }
}

#Preview {
    DocumentListView()
}