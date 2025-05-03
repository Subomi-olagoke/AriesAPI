import Foundation
import AVFoundation
import UIKit


class FeedViewModel: ObservableObject {
    // MARK: - Properties
    
    // Core properties
    @Published var error: String?
    let baseURL = "https://ariesmvp-9903a26b3095.herokuapp.com/api"
    private let authManager = AuthManager.shared
    private let userDefaults = UserDefaults.standard
    
    // User & Auth related properties
    @Published var userProfile: ProfileResponse?
    @Published var isLoadingProfile = false
    public var storedBearer: String
    public var userId: String
    public var username: String
    public var userRole: String
    @Published var selectedUserRole: String = "explorer"
    
    // Feed & Posts related properties
    @Published var posts: [Post] = []
    @Published var isLoadingPosts = false
    @Published var likedPostIds: Set<Int> = []
    private let likedPostsKey = "likedPosts"
    
    // Comments related properties
    @Published var comments: [Comment] = []
    @Published var isLoadingComments = false
    
    // Bookmarks related properties
    @Published var bookmarkedPostIds: Set<Int> = []
    private let bookmarkedPostsKey = "bookmarkedPosts"
    
    // Notifications related properties
    @Published var notifications: [NotificationData] = []
    @Published var isLoadingNotifications = false
    @Published var selectedNotificationFilter: NotificationFilter = .all
    
    // Educators related properties
    @Published var availableEducators: [EducatorData] = []
    @Published var followedEducators: [EducatorData] = []
    @Published var isLoadingAvailableEducators = false
    @Published var isLoadingFollowedEducators = false
    @Published var isFollowing = false
    @Published var isUnfollowing = false
    
    // Live Classes related properties
    @Published var liveClasses: [LiveClass] = []
    @Published var isLoadingLiveClasses = false
    
    // Conversations related properties
    @Published var conversations: [Conversation] = []
    @Published var currentConversation: Conversation?
    @Published var currentConversationMessages: [Message] = []
    @Published var isLoadingConversations = false
    @Published var isLoadingSendMessage = false
    @Published var libraries: [Library] = []
    @Published var isLoadingLibraries = false
    
    // Channels related properties
    @Published var channels: [Channel] = []
    @Published var currentChannel: Channel?
    @Published var channelMessages: [ChannelMessage] = []
    @Published var channelMembers: [ChannelMember] = []
    @Published var isLoadingChannels = false
    @Published var isLoadingChannel = false
    @Published var isLoadingChannelMessages = false
    @Published var isLoadingSendChannelMessage = false
    @Published var isJoiningChannel = false
    @Published var channelShareLink: String = ""
    
    // Courses related properties
    @Published var publicSpeakingCourses: [Course] = []
    @Published var contentCreationCourses: [Course] = []
    @Published var courseCategories: [CourseCategory] = []
    @Published var isLoadingCourses = false
    @Published var coursesByTopic: [Course] = []
    @Published var recommendedCourses: [Course] = []
    @Published var hasNoCoursesAvailable = false
    
    // Readlists related properties
    @Published var currentReadlist: ReadlistDetail?
    @Published var isLoadingReadlistDetail = false
    
    // MARK: - Enums & Computed Properties
    
    // Define notification filter enum
    enum NotificationFilter: String, CaseIterable {
        case all = "All"
        case mentions = "Mentions"
        case comments = "Comments"
    }
    
    // Computed property for filtered notifications
    var filteredNotifications: [NotificationData] {
        switch selectedNotificationFilter {
        case .all:
            return notifications
        case .mentions:
            return notifications.filter { $0.type.contains("Mention") }
        case .comments:
            return notifications.filter { $0.type.contains("Comment") }
        }
    }
    
    // Computed property for unread notification count
    var unreadNotificationCount: Int {
        notifications.filter { $0.read_at == nil }.count
    }
    
    // MARK: - Initialization & Lifecycle
    
    init() {
        // Initialize token, userId, and username from AuthManager
        self.storedBearer = authManager.getAuthToken()
        self.userId = authManager.getUserId()
        self.username = authManager.getUsername() ?? ""
        self.userRole = authManager.getUserRole() ?? ""
        
        // Load saved liked posts
        if let savedIds = userDefaults.array(forKey: likedPostsKey) as? [Int] {
            likedPostIds = Set(savedIds)
        }
        
        // Load saved bookmarked posts
        if let savedBookmarkIds = userDefaults.array(forKey: bookmarkedPostsKey) as? [Int] {
            bookmarkedPostIds = Set(savedBookmarkIds)
        }
        
        // Set up observation of auth changes
        NotificationCenter.default.addObserver(self,
                                               selector: #selector(authStateChanged),
                                               name: NSNotification.Name("AuthStateChanged"),
                                               object: nil)
    }
    
    // Update token, userId, and username when auth state changes
    @objc private func authStateChanged() {
        storedBearer = authManager.getAuthToken()
        userId = authManager.getUserId()
        username = authManager.getUsername() ?? ""
    }
    
    deinit {
        NotificationCenter.default.removeObserver(self)
    }
    
    private func handleApiResponse(_ data: Data?, _ response: URLResponse?, _ error: Error?) -> Bool {
        // Check for unauthorized response (401)
        if let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode == 401 {
            // Token is invalid, sign out the user
            DispatchQueue.main.async {
                self.error = "Session expired. Please sign in again."
                self.authManager.handleUnauthorizedResponse()
            }
            return false
        }
        return true
    }
    
    
    
    // MARK: - Authentication & Profile Management
    // ======================================
    // Beginning of Authentication & Profile Functions
    // ======================================
    
    // Function to manually refresh the token
    func refreshToken() {
        storedBearer = authManager.getAuthToken()
        userId = authManager.getUserId()
        username = authManager.getUsername() ?? ""
    }
    
    func updateUserBio(bio: String, completion: @escaping (Bool, String?) -> Void) {
         // Set loading state
         isLoadingProfile = true
         error = nil

         // Create parameters with just the bio
         let parameters: [String: Any] = ["bio": bio]

         // Encode parameters to JSON
         guard let putData = try? JSONSerialization.data(withJSONObject: parameters) else {
             DispatchQueue.main.async {
                 self.isLoadingProfile = false
                 self.error = "Failed to encode bio data"
                 completion(false, "Failed to encode bio data")
             }
             return
         }

         // Create the request
         var request = URLRequest(url: URL(string: "\(baseURL)/profile")!,
                                  timeoutInterval: Double.infinity)
         request.addValue("application/json", forHTTPHeaderField: "Content-Type")
         request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
         request.httpMethod = "PUT"
         request.httpBody = putData

         // Make the request
         URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
             guard let self = self else {
                 DispatchQueue.main.async {
                     completion(false, "View model was deallocated")
                 }
                 return
             }

             DispatchQueue.main.async {
                 self.isLoadingProfile = false

                 if let error = error {
                     self.error = error.localizedDescription
                     completion(false, error.localizedDescription)
                     return
                 }

                 // Check HTTP status code
                 if let httpResponse = response as? HTTPURLResponse,
                    httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                     // Success - refresh profile to get updated data
                     self.fetchProfile()
                     completion(true, "Bio updated successfully")
                 } else {
                     // Error response
                     let errorMessage = "Failed to update bio"
                     self.error = errorMessage
                     completion(false, errorMessage)
                 }
             }
         }.resume()
     }
    
    func registerDevice(deviceToken: String, completion: @escaping (Bool, String?) -> Void) {
        // Create parameters for the request
        let parameters: [String: Any] = [
            "device_token": deviceToken,
            "device_type": "ios"
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            completion(false, "Failed to encode device registration data")
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/device/register")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpMethod = "POST"
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check HTTP status code
                guard let httpResponse = response as? HTTPURLResponse else {
                    completion(false, "Invalid response")
                    return
                }
                
                // Debug: Log the response
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    print("Device registration response: \(responseString)")
                }
                
                // Handle response based on status code
                if httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    // Success
                    completion(true, "Device registered successfully")
                } else {
                    // Error response
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        completion(false, "Registration failed: \(responseString)")
                    } else {
                        completion(false, "Registration failed with status code: \(httpResponse.statusCode)")
                    }
                }
            }
        }.resume()
    }
    
    func fetchPostById(_ postId: Int, completion: @escaping (Post?, String?) -> Void) {
        // Create API request
        var request = URLRequest(url: URL(string: "\(baseURL)/fetch-post/\(postId)")!)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        // Execute network request
        URLSession.shared.dataTask(with: request) { data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    completion(nil, "Network error: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    completion(nil, "No data received")
                    return
                }
                
                // Try to decode the post
                if let post = try? JSONDecoder().decode(Post.self, from: data) {
                    completion(post, nil)
                    return
                }
                
                // Simple fallback for {post: Post} format
                struct PostWrapper: Codable {
                    let post: Post
                }
                if let wrapper = try? JSONDecoder().decode(PostWrapper.self, from: data) {
                    completion(wrapper.post, nil)
                    return
                }
                
                // If we get here, decoding failed
                completion(nil, "Failed to decode post data")
            }
        }.resume()
    }
    
    func fetchProfile(username: String? = nil) {
        isLoadingProfile = true
        error = nil
        
        // Use the provided username or fall back to the stored username
        let profileUsername = username ?? self.username
        
        var request = URLRequest(url: URL(string: "\(baseURL)/profile/username/\(profileUsername)")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingProfile = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ProfileResponse.self, from: data)
                    self.userProfile = response
                } catch {
                    self.error = "Failed to decode profile response: \(error.localizedDescription)"
                    print("Profile decoding error: \(error)")
                    
                    if let responseString = String(data: data, encoding: .utf8) {
                        print("Raw profile response: \(responseString)")
                    }
                }
            }
        }.resume()
    }
    
    func uploadAvatar(imageURL: URL) {
        error = nil
        
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Add the avatar file parameter
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition:form-data; name=\"avatar\"; filename=\"\(imageURL.lastPathComponent)\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
        
        // Read and append the file data
        do {
            let fileData = try Data(contentsOf: imageURL)
            body.append(fileData)
            body.append("\r\n".data(using: .utf8)!)
        } catch {
            self.error = "Failed to read image file: \(error.localizedDescription)"
            return
        }
        
        // Add the closing boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        // Create and configure the request
        var request = URLRequest(url: URL(string: "\(baseURL)/profile/avatar")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpMethod = "POST"
        request.httpBody = body
        
        // Perform the request
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Upload avatar response: \(responseString)")
                }
                
                // Refresh profile to get updated avatar
                self.fetchProfile()
            }
        }.resume()
    }
    
    func setupUserProfile(
        role: String,
        selectedTopicIds: [Int],
        description: String,
        qualifications: String,
        objectives: String,
        socialLinks: [String],
        completion: @escaping (Bool, String?) -> Void
    ) {
        // Use the profile loading state
        isLoadingProfile = true
        error = nil
        
        // Create the request parameters using the stored userId
        let parameters: [String: Any] = [
            "user_id": userId,
            "role": role,
            "selected_topic_ids": selectedTopicIds,
            "description": description,
            "qualifications": qualifications,
            "objectives": objectives,
            "social_links": socialLinks
        ]
        
        // Encode the parameters to JSON
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingProfile = false
                self.error = "Failed to encode setup data"
                completion(false, "Failed to encode setup data")
            }
            return
        }
        
        // Create the request
        var request = URLRequest(url: URL(string: "\(baseURL)/setup")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "POST"
        request.httpBody = postData
        
        // Make the request
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingProfile = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check HTTP status code
                guard let httpResponse = response as? HTTPURLResponse else {
                    self.error = "Invalid response"
                    completion(false, "Invalid response")
                    return
                }
                
                // Log the response for debugging
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    print("Setup profile response: \(responseString)")
                }
                
                // Handle response based on status code
                if httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    // Success response
                    if let data = data {
                        do {
                            // Try to decode a success response
                            struct SetupResponse: Codable {
                                let message: String?
                                let success: Bool?
                            }
                            
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(SetupResponse.self, from: data)
                            
                            // Update profile after successful setup
                            self.fetchProfile()
                            
                            completion(true, response.message ?? "Profile setup successfully")
                        } catch {
                            // If parsing fails, still consider it a success based on status code
                            self.fetchProfile()
                            completion(true, "Profile setup successfully")
                        }
                    } else {
                        self.fetchProfile()
                        completion(true, "Profile setup successfully")
                    }
                } else {
                    // Error response
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        self.error = "Setup failed: \(responseString)"
                        completion(false, "Setup failed: \(responseString)")
                    } else {
                        self.error = "Setup failed with status code: \(httpResponse.statusCode)"
                        completion(false, "Setup failed with status code: \(httpResponse.statusCode)")
                    }
                }
            }
        }.resume()
    }
    
    func updateEducatorProfile(
        bio: String? = nil,
        qualifications: [[String: Any]]? = nil,
        teachingStyle: String? = nil,
        availability: [[String: Any]]? = nil,
        hireRate: Double? = nil,
        hireCurrency: String? = nil,
        socialLinks: [String: String]? = nil,
        completion: @escaping (Bool, String?) -> Void
    ) {
        // Set loading state
        isLoadingProfile = true
        error = nil
        
        // Create parameters dictionary, only including non-nil values
        var parameters: [String: Any] = [:]
        
        if let bio = bio {
            parameters["bio"] = bio
        }
        
        if let qualifications = qualifications {
            parameters["qualifications"] = qualifications
        }
        
        if let teachingStyle = teachingStyle {
            parameters["teaching_style"] = teachingStyle
        }
        
        if let availability = availability {
            parameters["availability"] = availability
        }
        
        if let hireRate = hireRate {
            parameters["hire_rate"] = hireRate
        }
        
        if let hireCurrency = hireCurrency {
            parameters["hire_currency"] = hireCurrency
        }
        
        if let socialLinks = socialLinks {
            parameters["social_links"] = socialLinks
        }
        
        // Encode the parameters to JSON
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingProfile = false
                self.error = "Failed to encode profile data"
                completion(false, "Failed to encode profile data")
            }
            return
        }
        
        // Create the request
        var request = URLRequest(url: URL(string: "\(baseURL)/profile/educator")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "POST"
        request.httpBody = postData
        
        // Make the request
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingProfile = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check HTTP status code
                guard let httpResponse = response as? HTTPURLResponse else {
                    self.error = "Invalid response"
                    completion(false, "Invalid response")
                    return
                }
                
                // Log the response for debugging
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    print("Update educator profile response: \(responseString)")
                }
                
                // Handle response based on status code
                if httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    // Success response
                    if let data = data {
                        do {
                            // Try to decode a success response
                            struct UpdateResponse: Codable {
                                let message: String?
                                let profile: ProfileData?
                            }
                            
                            struct ProfileData: Codable {
                                // Add relevant profile fields here
                                let id: Int?
                                let user_id: String?
                                let bio: String?
                                // Other fields as needed
                            }
                            
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(UpdateResponse.self, from: data)
                            
                            // Update profile after successful update
                            self.fetchProfile()
                            
                            completion(true, response.message ?? "Educator profile updated successfully")
                        } catch {
                            // If parsing fails, still consider it a success based on status code
                            self.fetchProfile()
                            completion(true, "Educator profile updated successfully")
                        }
                    } else {
                        self.fetchProfile()
                        completion(true, "Educator profile updated successfully")
                    }
                } else {
                    // Error response
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        self.error = "Update failed: \(responseString)"
                        completion(false, "Update failed: \(responseString)")
                    } else {
                        self.error = "Update failed with status code: \(httpResponse.statusCode)"
                        completion(false, "Update failed with status code: \(httpResponse.statusCode)")
                    }
                }
            }
        }.resume()
    }
    
    // Verification Routes
    func submitVerification(documentData: Data, documentType: String, documentName: String, completion: @escaping (Bool, String?) -> Void) {
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Add document data
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition:form-data; name=\"document\"; filename=\"\(documentName)\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: \(getMimeType(for: documentName))\r\n\r\n".data(using: .utf8)!)
        body.append(documentData)
        body.append("\r\n".data(using: .utf8)!)
        
        // Add document type
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition:form-data; name=\"document_type\"\r\n\r\n".data(using: .utf8)!)
        body.append("\(documentType)\r\n".data(using: .utf8)!)
        
        // Add closing boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        // Create request
        var request = URLRequest(url: URL(string: "\(baseURL)/verification/submit")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, "Verification document submitted successfully")
                } else {
                    var errorMessage = "Failed to submit verification"
                    if let data = data,
                       let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let message = json["message"] as? String {
                        errorMessage = message
                    }
                    completion(false, errorMessage)
                }
            }
        }.resume()
    }
    
    func getVerificationStatus(completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/verification/status")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Authentication & Profile Functions
    // ======================================

    // MARK: - Posts & Feed Management
    // ======================================
    // Beginning of Posts & Feed Functions
    // ======================================
    
    func fetchPosts() {
        isLoadingPosts = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/feed")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingPosts = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Network error: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Debug: Log the raw response to understand its structure
                if let responseString = String(data: data, encoding: .utf8) {
                    print("API response preview: \(String(responseString.prefix(1000)))...")
                }
                
                // Create a JSONDecoder here so it's in scope for all the decode attempts
                let decoder = JSONDecoder()
                
                // First try: Parse using JSONDecoder directly
                do {
                    let response = try decoder.decode(FeedResponse.self, from: data)
                    self.posts = response.posts
                    print("Successfully decoded \(response.posts.count) posts")
                    return
                } catch {
                    print("Initial decoding error: \(error)")
                    
                    // Second attempt: Try a more detailed approach for debugging
                    do {
                        let jsonObj = try JSONSerialization.jsonObject(with: data, options: [])
                        
                        if let jsonDict = jsonObj as? [String: Any],
                           let postsArray = jsonDict["posts"] as? [[String: Any]] {
                            
                            print("Successfully parsed JSON manually, found \(postsArray.count) posts")
                            
                            // Remove the problematic mutual_comments field before decoding
                            let cleanedPostsArray = postsArray.map { post -> [String: Any] in
                                var postCopy = post
                                // Either remove mutual_comments entirely or replace with empty array
                                postCopy["mutual_comments"] = []
                                return postCopy
                            }
                            
                            // Convert back to JSON data and try decoding again
                            let cleanedData = try JSONSerialization.data(withJSONObject: ["posts": cleanedPostsArray])
                            let response = try decoder.decode(FeedResponse.self, from: cleanedData)
                            self.posts = response.posts
                            print("Successfully decoded \(response.posts.count) posts after cleaning data")
                            return
                        } else {
                            print("Could not interpret JSON as expected dictionary structure")
                        }
                    } catch {
                        print("Fallback parsing also failed: \(error)")
                    }
                    
                    // Final fallback: Just try to get something to display
                    do {
                        // Try to extract just the basic parts of posts
                        let jsonObj = try JSONSerialization.jsonObject(with: data, options: [])
                        if let jsonDict = jsonObj as? [String: Any],
                           let postsArray = jsonDict["posts"] as? [[String: Any]] {
                            
                            // Create a minimal version of posts with just the essential fields
                            var basicPosts: [Post] = []
                            
                            for postDict in postsArray {
                                // Extract the essential fields with fallbacks
                                let id = postDict["id"] as? Int ?? 0
                                let body = postDict["body"] as? String ?? ""
                                let createdAt = postDict["created_at"] as? String ?? ""
                                let updatedAt = postDict["updated_at"] as? String ?? ""
                                let userId = postDict["user_id"] as? String ?? ""
                                let shareurl = postDict["share_url"] as? String ?? ""
                                // Create a minimal user if possible
                                var user = User(firstName: "Unknown", lastName: "User", username: "unknown", avatar: nil, role: "")
                                if let userDict = postDict["user"] as? [String: Any] {
                                    let firstName = userDict["first_name"] as? String ?? "Unknown"
                                    let lastName = userDict["last_name"] as? String ?? "User"
                                    let username = userDict["username"] as? String ?? "unknown"
                                    let avatar = userDict["avatar"] as? String
                                    let role = userDict["role"] as? String ?? ""
                                    
                                    user = User( firstName: firstName, lastName: lastName, username: username, avatar: avatar, role: role)
                                }
                                
                                // Create a basic post
                                let post = Post(
                                    id: id,
                                    createdAt: createdAt,
                                    updatedAt: updatedAt,
                                    title: nil,
                                    body: body,
                                    mediaLink: postDict["media_link"] as? String,
                                    visibility: postDict["visibility"] as? String ?? "public",
                                    mediaType: postDict["media_type"] as? String ?? "text",
                                    mediaThumbnail: nil,
                                    userId: userId,
                                    user: user,
                                    isLiked: false,
                                    shareUrl: shareurl,
                                    isFromFollowedUser: false,
                                    isFromRelatedTopic: false,
                                    mutualComments: [],
                                    comments: []
                                )
                                
                                basicPosts.append(post)
                            }
                            
                            self.posts = basicPosts
                            print("Created \(basicPosts.count) minimal posts as fallback")
                        } else {
                            self.error = "Could not parse response format"
                        }
                    } catch {
                        self.error = "Failed to parse posts: \(error.localizedDescription)"
                        print("Final fallback failed: \(error)")
                    }
                }
            }
        }.resume()
    }
    
    func createImagePost(mediaLink: String, visibility: String = "followers") {
        error = nil
        
        let parameters: [String: Any] = [
            "user_id": userId,
            "media_type": "image",
            "media_link": mediaLink,
            "visibility": visibility
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            error = "Failed to encode post data"
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/post")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        request.httpMethod = "POST"
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                self.fetchPosts()
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Create image post response: \(responseString)")
                }
            }
        }.resume()
    }
    
    func getMimeType(for fileName: String) -> String {
        let ext = (fileName as NSString).pathExtension.lowercased()
        
        switch ext {
        case "jpg", "jpeg":
            return "image/jpeg"
        case "png":
            return "image/png"
        case "gif":
            return "image/gif"
        case "mp4":
            return "video/mp4"
        case "mov":
            return "video/quicktime"
        case "pdf":
            return "application/pdf"
        case "doc":
            return "application/msword"
        case "docx":
            return "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        case "xls", "xlsx":
            return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        case "txt":
            return "text/plain"
        case "zip":
            return "application/zip"
        default:
            return "application/octet-stream" // Generic binary data
        }
    }
    
    // Helper function to generate a thumbnail from video data
    private func generateThumbnailData(from videoData: Data, size: CGSize = CGSize(width: 320, height: 180), completion: @escaping (Data?) -> Void) {
        // Create a temporary URL to store the video data
        let temporaryDirectoryURL = FileManager.default.temporaryDirectory
        let temporaryFileURL = temporaryDirectoryURL.appendingPathComponent(UUID().uuidString).appendingPathExtension("mp4")
        
        do {
            // Write video data to the temporary file
            try videoData.write(to: temporaryFileURL)
            
            // Create AVAsset from the temporary file
            let asset = AVAsset(url: temporaryFileURL)
            let imageGenerator = AVAssetImageGenerator(asset: asset)
            imageGenerator.appliesPreferredTrackTransform = true
            
            // Set the maximum size to avoid memory issues
            imageGenerator.maximumSize = size
            
            // Try to get the thumbnail at 1 second
            let time = CMTimeMake(value: 1, timescale: 1)
            
            imageGenerator.generateCGImagesAsynchronously(forTimes: [NSValue(time: time)]) { _, cgImage, _, _, error in
                // Clean up the temporary file
                try? FileManager.default.removeItem(at: temporaryFileURL)
                
                if let error = error {
                    print("Error generating thumbnail: \(error.localizedDescription)")
                    completion(nil)
                    return
                }
                
                guard let cgImage = cgImage else {
                    print("Failed to generate thumbnail image")
                    completion(nil)
                    return
                }
                
                // Convert CGImage to UIImage
                let thumbnail = UIImage(cgImage: cgImage)
                
                // Convert UIImage to JPEG data
                completion(thumbnail.jpegData(compressionQuality: 0.7))
            }
        } catch {
            print("Error setting up thumbnail generation: \(error.localizedDescription)")
            
            // Clean up the temporary file
            try? FileManager.default.removeItem(at: temporaryFileURL)
            
            completion(nil)
        }
    }
    
    func createPost(textContent: String, title: String = "", mediaType: String = "text", mediaData: Data? = nil, fileName: String? = nil, mediaThumbnail: String = "", visibility: String = "public", completion: ((Bool, String) -> Void)? = nil) {
        error = nil
        
        // Create boundary string for multipart form
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Debug flag - set to true to print detailed request information
        let debug = true
        
        // Add text fields directly as in Postman code
        // text_content
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition:form-data; name=\"text_content\"\r\n\r\n".data(using: .utf8)!)
        body.append("\(textContent)\r\n".data(using: .utf8)!)
        
        // title
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition:form-data; name=\"title\"\r\n\r\n".data(using: .utf8)!)
        body.append("\(title)\r\n".data(using: .utf8)!)
        
        // media_type
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition:form-data; name=\"media_type\"\r\n\r\n".data(using: .utf8)!)
        body.append("\(mediaType)\r\n".data(using: .utf8)!)
        
        // visibility
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition:form-data; name=\"visibility\"\r\n\r\n".data(using: .utf8)!)
        body.append("\(visibility)\r\n".data(using: .utf8)!)
        
        // media_thumbnail
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition:form-data; name=\"media_thumbnail\"\r\n\r\n".data(using: .utf8)!)
        body.append("\(mediaThumbnail)\r\n".data(using: .utf8)!)
        
        // Add media file if provided
        if let mediaData = mediaData, let fileName = fileName {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition:form-data; name=\"media_file\"; filename=\"\(fileName)\"\r\n".data(using: .utf8)!)
            body.append("Content-Type: \"content-type header\"\r\n\r\n".data(using: .utf8)!)
            body.append(mediaData)
            body.append("\r\n".data(using: .utf8)!)
            
            if debug {
                print("Added media file: \(fileName)")
                print("Media file size: \(mediaData.count) bytes")
            }
        }
        
        // Add closing boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        // Create and configure the request
        var request = URLRequest(url: URL(string: "\(baseURL)/post")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("access_token=\(storedBearer)", forHTTPHeaderField: "Cookie")
        request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpMethod = "POST"
        request.httpBody = body
        
        if debug {
            print("====== POST REQUEST DETAILS ======")
            print("URL: \(request.url?.absoluteString ?? "")")
            print("Headers: \(request.allHTTPHeaderFields ?? [:])")
            print("Boundary: \(boundary)")
            print("Body size: \(body.count) bytes")
            print("=================================")
        }
        
        // Send the request
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else { return }
            
            DispatchQueue.main.async {
                
                // Process response
                if let error = error {
                    self.error = error.localizedDescription
                    print("Network error: \(error.localizedDescription)")
                    completion?(false, error.localizedDescription)
                    return
                }
                
                // Get HTTP status code
                let statusCode = (response as? HTTPURLResponse)?.statusCode ?? 0
                
                // Process response data
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    print("Response status code: \(statusCode)")
                    print("Response data: \(responseString)")
                    
                    if statusCode >= 200 && statusCode < 300 {
                        // Success - parse the response if needed
                        do {
                            if let jsonResponse = try JSONSerialization.jsonObject(with: data, options: []) as? [String: Any] {
                                // Extract any useful information from the response
                                let message = jsonResponse["message"] as? String ?? "Post created successfully"
                                
                                // Refresh the post feed
                                self.fetchPosts()
                                
                                // Return success result
                                completion?(true, message)
                            } else {
                                // Success but couldn't parse JSON
                                self.fetchPosts()
                                completion?(true, "Post created successfully")
                            }
                        } catch {
                            print("JSON parsing error: \(error)")
                            // Still consider it a success if HTTP status is in the success range
                            self.fetchPosts()
                            completion?(true, "Post created successfully")
                        }
                    } else {
                        // Error response
                        let errorMessage: String
                        
                        // Try to extract error message from response
                        do {
                            if let jsonResponse = try JSONSerialization.jsonObject(with: data, options: []) as? [String: Any],
                               let message = jsonResponse["message"] as? String {
                                errorMessage = message
                            } else {
                                errorMessage = "Server returned status code: \(statusCode)"
                            }
                        } catch {
                            errorMessage = "Server returned status code: \(statusCode)"
                        }
                        
                        self.error = errorMessage
                        print("Error: \(errorMessage)")
                        completion?(false, errorMessage)
                    }
                } else {
                    // No data received
                    let errorMessage = "No response data received"
                    self.error = errorMessage
                    print(errorMessage)
                    completion?(false, errorMessage)
                }
            }
        }.resume()
    }
    
    func likePost(postId: Int) {
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/post/\(postId)/like")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        request.httpMethod = "POST"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Like post response: \(responseString)")
                }
                
                if self.likedPostIds.contains(postId) {
                    self.likedPostIds.remove(postId)
                } else {
                    self.likedPostIds.insert(postId)
                }
                self.saveLikedPosts()
            }
        }.resume()
    }
    
    func isPostLiked(_ postId: Int) -> Bool {
        return likedPostIds.contains(postId)
    }
    
    private func saveLikedPosts() {
        userDefaults.set(Array(likedPostIds), forKey: likedPostsKey)
    }
    
    // ======================================
    // End of Posts & Feed Functions
    // ======================================

    // MARK: - Comments Management
    // ======================================
    // Beginning of Comments Functions
    // ======================================
    
    func fetchComments(for postId: Int) {
        isLoadingComments = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/comments/\(postId)")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingComments = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching comments: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    print("No data received from comments endpoint")
                    return
                }
                
                // Log the direct result from the server
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Server response for comments: \(responseString)")
                }
                
                // First check if we can parse this as a JSON object with a "message" key
                // This would be the "no comments" response
                do {
                    if let jsonDict = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let message = jsonDict["message"] as? String {
                        print("API message: \(message)")
                        
                        // This is a valid "no comments" response
                        self.comments = []
                        return
                    }
                } catch {
                    // This is fine, it means it's not a dictionary object
                    print("Not a dictionary object: \(error)")
                }
                
                // If we reach here, try to decode as an array of Comment objects
                do {
                    let decoder = JSONDecoder()
                    let commentsArray = try decoder.decode([Comment].self, from: data)
                    self.comments = commentsArray
                    print("Successfully decoded \(commentsArray.count) comments")
                } catch {
                    self.error = "Failed to decode comments: \(error.localizedDescription)"
                    print("Comments decoding error: \(error)")
                    self.comments = []
                }
            }
        }.resume()
    }
    
    func fetchCommentsForPost(postId: Int) {
        isLoadingComments = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/comments/\(postId)")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else { return }
            
            DispatchQueue.main.async {
                self.isLoadingComments = false
                
                guard let data = data else {
                    self.error = String(describing: error)
                    return
                }
                
                do {
                    // You may need to adjust this decoder based on your actual response structure
                    let decoder = JSONDecoder()
                    let commentsResponse = try decoder.decode(CommentsResponse.self, from: data)
                    self.comments = commentsResponse.comments ?? []
                } catch {
                    self.error = "Failed to decode comments: \(error.localizedDescription)"
                    print("Comments decoding error: \(error)")
                    
                    if let responseString = String(data: data, encoding: .utf8) {
                        print("Raw comments response: \(responseString)")
                    }
                }
            }
        }
        
        task.resume()
    }
    
    func addComment(postId: Int, content: String) {
        error = nil
        
        let parameters: [String: Any] = [
            "content": content
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            error = "Failed to encode comment data"
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/comment/\(postId)")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        request.httpMethod = "POST"
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                // Debug: Log the raw response to understand its structure
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    print("Add comment response: \(responseString)")
                    
                    // Try to decode the new comment from response if available
                    if let newComment = try? JSONDecoder().decode(Comment.self, from: data) {
                        // Add the new comment to our local array
                        self.comments.append(newComment)
                        return
                    }
                }
                
                // If we couldn't add the comment locally from the response,
                // fetch all comments again to refresh the list
                self.fetchComments(for: postId)
            }
        }.resume()
    }
    
    func getCommentCount(postId: Int, completion: @escaping (Int?) -> Void) {
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/comment/\(postId)/count")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        let task = URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(nil)
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(nil)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(nil)
                    return
                }
                
                do {
                    // Log the response for debugging
                    if let responseString = String(data: data, encoding: .utf8) {
                        print("Comment count response: \(responseString)")
                    }
                    
                    // Define a response structure for the comment count
                    struct CommentCountResponse: Codable {
                        let commentCount: Int
                        
                        enum CodingKeys: String, CodingKey {
                            case commentCount = "comment_count"
                        }
                    }
                    
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(CommentCountResponse.self, from: data)
                    completion(response.commentCount)
                } catch {
                    self.error = "Failed to decode comment count: \(error.localizedDescription)"
                    print("Comment count decoding error: \(error)")
                    completion(nil)
                }
            }
        }
        
        task.resume()
    }
    
    func deleteComment(commentId: Int, completion: @escaping (Bool, String?) -> Void) {
        isLoadingComments = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/comments/\(commentId)")!)
        request.httpMethod = "DELETE"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingComments = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check for successful HTTP status code
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    // If successful, remove the comment from our local array
                    if let index = self.comments.firstIndex(where: { $0.id == commentId }) {
                        self.comments.remove(at: index)
                    }
                    
                    // Log success for debugging
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        print("Delete comment response: \(responseString)")
                    }
                    
                    completion(true, "Comment deleted successfully")
                } else {
                    // Try to extract error message from response
                    var errorMessage = "Failed to delete comment"
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        print("Delete comment error: \(responseString)")
                        errorMessage = "Failed to delete comment: \(responseString)"
                    }
                    
                    self.error = errorMessage
                    completion(false, errorMessage)
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Comments Functions
    // ======================================

    // MARK: - Notifications Management
    // ======================================
    // Beginning of Notifications Functions
    // ======================================
    
    func fetchNotifications() {
        isLoadingNotifications = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/notifications")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingNotifications = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching notifications: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Print raw response for debugging
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Notifications response: \(responseString)")
                }
                
                do {
                    // Create a decoder with more relaxed type checking
                    let decoder = JSONDecoder()
                    
                    // First try: Parse with the standard decoder
                    let response = try decoder.decode(NotificationResponse.self, from: data)
                    
                    // Sort notifications by created_at date in descending order
                    self.notifications = response.notifications.sorted {
                        $0.created_at > $1.created_at
                    }
                    
                    print("Successfully decoded \(self.notifications.count) notifications")
                } catch {
                    self.error = "Failed to decode response: \(error.localizedDescription)"
                    print("Decoding error: \(error)")
                    
                    // Try a more lenient approach - parse as Dictionary and build NotificationData objects manually
                    do {
                        if let jsonDict = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                           let notificationsArray = jsonDict["notifications"] as? [[String: Any]] {
                            
                            // Process notifications manually if needed
                            print("Manual parsing would be needed here - found \(notificationsArray.count) notifications")
                            print("Consider updating your model to handle both String and Int types for post_id")
                        }
                    } catch {
                        print("Manual parsing also failed: \(error)")
                    }
                    
                    // More detailed error info for debugging
                    if let decodingError = error as? DecodingError {
                        switch decodingError {
                        case .typeMismatch(let type, let context):
                            print("Type mismatch: \(type), context: \(context)")
                        case .valueNotFound(let type, let context):
                            print("Value not found: \(type), context: \(context)")
                        case .keyNotFound(let key, let context):
                            print("Key not found: \(key), context: \(context)")
                        case .dataCorrupted(let context):
                            print("Data corrupted: \(context)")
                        @unknown default:
                            print("Unknown decoding error")
                        }
                    }
                }
            }
        }.resume()
    }
    
    func markNotificationAsRead(_ notificationId: String) {
        var request = URLRequest(url: URL(string: "\(baseURL)/notifications/\(notificationId)/mark-as-read")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "POST"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                if error == nil {
                    if let index = self.notifications.firstIndex(where: { $0.id == notificationId }) {
                        self.notifications[index].read_at = ISO8601DateFormatter().string(from: Date())
                    }
                }
            }
        }.resume()
    }
    
    func markAllNotificationsAsRead() {
        var request = URLRequest(url: URL(string: "\(baseURL)/notifications/mark-all-as-read")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "POST"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                if error == nil {
                    let currentDate = ISO8601DateFormatter().string(from: Date())
                    self.notifications = self.notifications.map { notification in
                        var updatedNotification = notification
                        updatedNotification.read_at = currentDate
                        return updatedNotification
                    }
                }
            }
        }.resume()
    }
    
    func refreshNotifications() {
        fetchNotifications()
    }
    
    // Format notification message
    func formatNotificationMessage(_ notification: NotificationData) -> String {
        // Use the message directly from the notification data
        return notification.data.message ?? ""
    }
    
    // Set notification filter
    func setNotificationFilter(_ filter: NotificationFilter) {
        selectedNotificationFilter = filter
    }
    
    // Format time ago for notifications
    func timeAgo(from dateString: String) -> String {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        
        guard let date = formatter.date(from: dateString) else {
            return "Unknown"
        }
        
        let calendar = Calendar.current
        let now = Date()
        let components = calendar.dateComponents([.year, .month, .day, .hour, .minute], from: date, to: now)
        
        if let year = components.year, year > 0 {
            return "\(year)y"
        } else if let month = components.month, month > 0 {
            return "\(month)mo"
        } else if let day = components.day, day > 0 {
            return "\(day)d"
        } else if let hour = components.hour, hour > 0 {
            return "\(hour)h"
        } else if let minute = components.minute, minute > 0 {
            return "\(minute)m"
        } else {
            return "now"
        }
    }
    
    // ======================================
    // End of Notifications Functions
    // ======================================

    // MARK: - Bookmarks Management
    // ======================================
    // Beginning of Bookmarks Functions
    // ======================================
    
    func isPostBookmarked(_ postId: Int) -> Bool {
        return bookmarkedPostIds.contains(postId)
    }
    
    func saveBookmarkedPosts() {
        userDefaults.set(Array(bookmarkedPostIds), forKey: bookmarkedPostsKey)
    }
    
    func togglePostBookmark(postId: Int, contentType: String = "post", relevanceScore: Double = 0.85) {
        let isCurrentlyBookmarked = isPostBookmarked(postId)
        
        if isCurrentlyBookmarked {
            // Remove from bookmarks
            bookmarkedPostIds.remove(postId)
            saveBookmarkedPosts()
            return
        }
        
        // Otherwise, add to bookmarks
        let parameters: [String: Any] = [
            "content_id": postId,
            "content_type": contentType,
            "relevance_score": relevanceScore
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            error = "Failed to encode bookmark data"
            return
        }
        
        var request = URLRequest(
            url: URL(string: "\(baseURL)/libraries/1/content")!,
            timeoutInterval: Double.infinity
        )
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("access_token=\(storedBearer)", forHTTPHeaderField: "Cookie")
        request.httpMethod = "POST"
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error bookmarking post: \(error.localizedDescription)")
                    return
                }
                
                // Check response status code
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    // Successful bookmark
                    self.bookmarkedPostIds.insert(postId)
                    self.saveBookmarkedPosts()
                    
                    // Log success message
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        print("Bookmark post response: \(responseString)")
                    }
                } else {
                    // Failed bookmark
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        print("Failed to bookmark post: \(responseString)")
                    }
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Bookmarks Functions
    // ======================================

    // MARK: - Educators Management
    // ======================================
    // Beginning of Educators Functions
    // ======================================
    
    // Fetch available educators (all educators)
    func fetchAvailableEducators() {
        isLoadingAvailableEducators = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/educators")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingAvailableEducators = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Debug: Log the raw response
                if let responseString = String(data: data, encoding: .utf8) {
                    print("API educators response: \(responseString)")
                }
            }
        }.resume()
    }
    
    // Fetch hireable educators - filtering educators who can be hired
    func fetchHireableEducators(completion: @escaping (Bool, [EducatorData]?, String?) -> Void) {
        isLoadingAvailableEducators = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/educators/with-follow-status")!,
                               timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, nil, "View model deallocated")
                return
            }
            
            if let error = error {
                print("Error fetching hireable educators: \(error.localizedDescription)")
                completion(false, nil, error.localizedDescription)
                return
            }
            
            guard let data = data else {
                completion(false, nil, "No data received")
                return
            }
            
            do {
                let response = try JSONDecoder().decode(EducatorsResponse.self, from: data)
                
                // Filter educators to include only those who:
                // 1. Have completed setup (can offer services)
                // 2. Are not the current user (can't hire yourself)
                let hireableEducators = response.educators.data.filter { educator in
                    return educator.setupCompleted == true && educator.isCurrentUser == false
                }
                
                completion(true, hireableEducators, nil)
            } catch {
                print("Hireable educators decoding error: \(error)")
                completion(false, nil, "Failed to decode educators response")
            }
        }.resume()
    }
    
    // Fetch all educators with follow status - for SearchPage integration
    func fetchAllEducatorsWithFollowStatus(completion: @escaping (Bool, [EducatorData]?, String?) -> Void) {
        isLoadingAvailableEducators = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/educators/with-follow-status")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingAvailableEducators = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    if let response = try? decoder.decode(EducatorsResponse.self, from: data) {
                        self.availableEducators = response.educators.data
                        completion(true, response.educators.data, nil)
                    } else {
                        completion(false, nil, "Failed to decode educators response")
                    }
                }
            }
        }.resume()
    }
    
    // Fetch followed educators - for SearchPage integration
    func fetchFollowedEducators(completion: @escaping (Bool, [EducatorData]?, String?) -> Void) {
        isLoadingFollowedEducators = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/educators/followed")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingFollowedEducators = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    if let response = try? decoder.decode(EducatorsResponse.self, from: data) {
                        self.followedEducators = response.educators.data
                        completion(true, response.educators.data, nil)
                    } else {
                        completion(false, nil, "Failed to decode educators response")
                    }
                }
            }
        }.resume()
    }
                
    // Helper function to parse educators response
    private func parseEducatorsResponse(_ data: Data) {
        do {
            let decoder = JSONDecoder()
            let response = try decoder.decode(EducatorsResponse.self, from: data)
            self.availableEducators = response.educators.data
            print("Successfully decoded educators response")
        } catch {
            self.error = "Failed to decode educators response: \(error.localizedDescription)"
            print("Educators decoding error: \(error)")
        }
    }
    
    // Fetch educators with follow status
    func fetchFollowedEducators() {
        isLoadingFollowedEducators = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/educators/with-follows")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingFollowedEducators = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Debug: Log the raw response
                if let responseString = String(data: data, encoding: .utf8) {
                    print("API educators with follows response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(EducatorsResponse.self, from: data)
                    
                    // Filter to just the followed educators
                    self.followedEducators = response.educators.data.filter { $0.isFollowed == true }
                    
                    print("Successfully decoded educators with follows response")
                } catch {
                    self.error = "Failed to decode educators with follows response: \(error.localizedDescription)"
                    print("Educators with follows decoding error: \(error)")
                }
            }
        }.resume()
    }
    
    // Follow a user
    func followUser(userId: String) {
        isFollowing = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/follow/\(userId)")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("access_token=\(storedBearer)", forHTTPHeaderField: "Cookie")
        request.httpMethod = "POST"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else { return }
            
            DispatchQueue.main.async {
                self.isFollowing = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    print("Follow user response: \(responseString)")
                }
                
                // Refresh educators data if we're viewing educators
                self.fetchFollowedEducators()
                self.fetchAvailableEducators()
            }
        }.resume()
    }
    
    // Unfollow a user
    func unfollowUser(userId: String) {
        isUnfollowing = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/unfollow/\(userId)")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("access_token=\(storedBearer)", forHTTPHeaderField: "Cookie")
        request.httpMethod = "POST"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else { return }
            
            DispatchQueue.main.async {
                self.isUnfollowing = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    print("Unfollow user response: \(responseString)")
                }
                
                // Refresh educators data if we're viewing educators
                self.fetchFollowedEducators()
                self.fetchAvailableEducators()
            }
        }.resume()
    }
    
    // ======================================
    // End of Educators Functions
    // ======================================

    // MARK: - Hire Services
    // ======================================
    // Beginning of Hire Services Functions
    // ======================================
    
    func verifyPayment(reference: String, completion: @escaping (Bool, String?, [String: Any]?) -> Void) {
        let urlString = "\(baseURL)/hire/verify-payment?reference=\(reference)"
        
        guard let url = URL(string: urlString) else {
            completion(false, "Invalid URL", nil)
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated", nil)
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)", nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                // For debugging
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Payment verification response: \(responseString)")
                }
                
                do {
                    // Try to parse the JSON response
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        // Check for message indicating success
                        let message = jsonResponse["message"] as? String
                        
                        // Extract hire request if available
                        let hireRequest = jsonResponse["hire_request"] as? [String: Any]
                        
                        // Determine success based on status code and response content
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            completion(true, message ?? "Payment verified successfully", hireRequest)
                        } else {
                            completion(false, message ?? "Payment verification failed", nil)
                        }
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func acceptHireRequest(requestId: Int, completion: @escaping (Bool, String?, [String: Any]?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/hire-requests/\(requestId)/accept")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated", nil)
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)", nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Accept hire request response: \(responseString)")
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        let message = jsonResponse["message"] as? String
                        let conversationId = jsonResponse["conversation_id"] as? String
                        let hireRequest = jsonResponse["hire_request"] as? [String: Any]
                        
                        let responseDict: [String: Any] = [
                            "conversation_id": conversationId as Any,
                            "hire_request": hireRequest as Any
                        ]
                        
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            completion(true, message ?? "Request accepted successfully", responseDict)
                        } else {
                            completion(false, message ?? "Failed to accept request", nil)
                        }
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func declineHireRequest(requestId: Int, completion: @escaping (Bool, String?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/hire-requests/\(requestId)/decline")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated")
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received")
                    return
                }
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Decline hire request response: \(responseString)")
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, "Request declined successfully")
                } else {
                    completion(false, "Failed to decline request")
                }
            }
        }.resume()
    }
    
    func cancelHireRequest(requestId: Int, completion: @escaping (Bool, String?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/hire-requests/\(requestId)")!)
        request.httpMethod = "DELETE"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated")
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received")
                    return
                }
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Cancel hire request response: \(responseString)")
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, "Request cancelled successfully")
                } else {
                    completion(false, "Failed to cancel request")
                }
            }
        }.resume()
    }
    
    func rateEducator(sessionId: Int, rating: Int, comment: String?, completion: @escaping (Bool, String?) -> Void) {
        var parameters: [String: Any] = ["rating": rating]
        
        if let comment = comment {
            parameters["comment"] = comment
        }
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            completion(false, "Failed to encode rating data")
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/hire-sessions/\(sessionId)/rate")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated")
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received")
                    return
                }
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Rate educator response: \(responseString)")
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, "Educator rated successfully")
                } else {
                    completion(false, "Failed to submit rating")
                }
            }
        }.resume()
    }
    
    func completeHireSession(sessionId: Int, completion: @escaping (Bool, String?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/hire-sessions/\(sessionId)/complete")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated")
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received")
                    return
                }
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Complete session response: \(responseString)")
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, "Session marked as completed")
                } else {
                    completion(false, "Failed to complete session")
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Hire Services Functions
    // ======================================

    // MARK: - Live Classes Management
    // ======================================
    // Beginning of Live Classes Functions
    // ======================================
    
    // Fetch live classes
    func fetchLiveClasses() {
        isLoadingLiveClasses = true
        
        // Create your request
        guard let url = URL(string: "\(baseURL)/live-classes") else {
            isLoadingLiveClasses = false
            return
        }
        
        var request = URLRequest(url: url)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    print("Live classes fetch error: \(error.localizedDescription)")
                    self.isLoadingLiveClasses = false
                    return
                }
                
                guard let data = data else {
                    self.isLoadingLiveClasses = false
                    return
                }
                
                do {
                    // Decode to the response wrapper first
                    let response = try JSONDecoder().decode(LiveClassesResponse.self, from: data)
                    // Then extract the live classes array from the data field
                    self.liveClasses = response.liveClasses.data
                    self.isLoadingLiveClasses = false
                } catch {
                    print("Live classes decoding error: \(error)")
                    self.isLoadingLiveClasses = false
                }
            }
        }.resume()
    }
    
    func createLiveClass(
        title: String,
        description: String,
        scheduledAt: Date,
        duration: Int,
        topicId: Int? = nil,
        maxParticipants: Int? = nil,
        isPublic: Bool = true,
        thumbnail: Data? = nil,
        prerequisites: [String]? = nil,
        materials: [String]? = nil,
        courseId: Int? = nil,
        password: String? = nil,
        recordingEnabled: Bool = false,
        settings: [String: Bool]? = nil,
        completion: @escaping (Bool, String?, [String: Any]?) -> Void
    ) {
        // Create multipart form data
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Format the date for the API
        let dateFormatter = ISO8601DateFormatter()
        dateFormatter.formatOptions = [.withInternetDateTime]
        let scheduledAtString = dateFormatter.string(from: scheduledAt)
        
        // Add text fields
        let textParams: [String: Any] = [
            "title": title,
            "description": description,
            "scheduled_at": scheduledAtString,
            "duration": duration,
            "is_public": isPublic,
            "recording_enabled": recordingEnabled
        ]
        
        // Add optional parameters if provided
        var allParams = textParams
        if let topicId = topicId { allParams["topic_id"] = topicId }
        if let maxParticipants = maxParticipants { allParams["max_participants"] = maxParticipants }
        if let prerequisites = prerequisites { allParams["prerequisites"] = prerequisites }
        if let materials = materials { allParams["materials"] = materials }
        if let courseId = courseId { allParams["course_id"] = courseId }
        if let password = password { allParams["password"] = password }
        
        // Add settings if provided
        if let settings = settings {
            // Convert settings dictionary to JSON string
            do {
                let settingsData = try JSONSerialization.data(withJSONObject: settings)
                if let settingsString = String(data: settingsData, encoding: .utf8) {
                    allParams["settings"] = settingsString
                }
            } catch {
                print("Error serializing settings: \(error)")
            }
        }
        
        // Add all parameters to the form data
        for (key, value) in allParams {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"\(key)\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(value)\r\n".data(using: .utf8)!)
        }
        
        // Add thumbnail if provided
        if let thumbnailData = thumbnail {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"thumbnail\"; filename=\"thumbnail.jpg\"\r\n".data(using: .utf8)!)
            body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
            body.append(thumbnailData)
            body.append("\r\n".data(using: .utf8)!)
        }
        
        // Add closing boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        // Create the request
        var request = URLRequest(url: URL(string: "\(baseURL)/live-classes")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        
        // Make the request
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated", nil)
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)", nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                // For debugging
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Create live class response: \(responseString)")
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        let message = jsonResponse["message"] as? String
                        let liveClass = jsonResponse["live_class"] as? [String: Any]
                        
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            completion(true, message ?? "Live class created successfully", liveClass)
                            
                            // Refresh the live classes list
                            self.fetchLiveClasses()
                        } else {
                            completion(false, message ?? "Failed to create live class", nil)
                        }
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    // Join a live class as a participant
    func joinLiveClass(liveClassId: Int, completion: @escaping (Bool, String?, String?) -> Void) {
        guard let url = URL(string: "\(baseURL)/live-classes/\(liveClassId)/join") else {
            completion(false, nil, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    print("Join live class error: \(error.localizedDescription)")
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                // Try to parse the response to get the meeting URL
                if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                   let meetingUrl = json["meeting_url"] as? String {
                    // Success - return the meeting URL
                    completion(true, meetingUrl, nil)
                } else {
                    // Try to parse an error message
                    if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let message = json["message"] as? String {
                        completion(false, nil, message)
                    } else {
                        completion(false, nil, "Failed to parse server response")
                    }
                }
            }
        }.resume()
    }
    
    // Start a live class as the host (educator)
    func startLiveClass(liveClassId: Int, completion: @escaping (Bool, String?, [String: Any]?) -> Void) {
        guard let url = URL(string: "\(baseURL)/live-classes/\(liveClassId)/start") else {
            completion(false, "Invalid URL", nil)
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    print("Start live class error: \(error.localizedDescription)")
                    completion(false, error.localizedDescription, nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                // For debugging
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Start live class response: \(responseString)")
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        let message = jsonResponse["message"] as? String
                        let meetingDetails = jsonResponse["meeting_details"] as? [String: Any]
                        
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            completion(true, message ?? "Live class started successfully", meetingDetails)
                            
                            // Refresh the live classes list to update status
                            self.fetchLiveClasses()
                        } else {
                            completion(false, message ?? "Failed to start live class", nil)
                        }
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    // End a live class (educator only)
    func endLiveClass(liveClassId: Int, completion: @escaping (Bool, String?) -> Void) {
        guard let url = URL(string: "\(baseURL)/live-classes/\(liveClassId)/end") else {
            completion(false, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated")
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    print("End live class error: \(error.localizedDescription)")
                    completion(false, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received")
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    var message = "Live class ended successfully"
                    if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let responseMessage = json["message"] as? String {
                        message = responseMessage
                    }
                    
                    completion(true, message)
                    
                    // Refresh the live classes list to update status
                    self.fetchLiveClasses()
                } else {
                    var errorMessage = "Failed to end live class"
                    if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let responseMessage = json["message"] as? String {
                        errorMessage = responseMessage
                    }
                    
                    completion(false, errorMessage)
                }
            }
        }.resume()
    }
    
    // Get meeting details for a live class
    func getLiveClassMeetingDetails(liveClassId: Int, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        guard let url = URL(string: "\(baseURL)/live-classes/\(liveClassId)/meeting-details") else {
            completion(false, nil, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    print("Get meeting details error: \(error.localizedDescription)")
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            
                            let meetingDetails = jsonResponse["meeting_details"] as? [String: Any]
                            completion(true, meetingDetails, nil)
                        } else {
                            let message = jsonResponse["message"] as? String
                            completion(false, nil, message ?? "Failed to get meeting details")
                        }
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    // Get participants in a live class
    func getLiveClassParticipants(liveClassId: Int, completion: @escaping (Bool, [[String: Any]]?, String?) -> Void) {
        guard let url = URL(string: "\(baseURL)/live-classes/\(liveClassId)/participants") else {
            completion(false, nil, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    print("Get participants error: \(error.localizedDescription)")
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            
                            let participants = jsonResponse["participants"] as? [[String: Any]]
                            completion(true, participants, nil)
                        } else {
                            let message = jsonResponse["message"] as? String
                            completion(false, nil, message ?? "Failed to get participants")
                        }
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    // Send a message in the live class chat
    func sendLiveClassChatMessage(liveClassId: Int, message: String, completion: @escaping (Bool, String?) -> Void) {
        guard let url = URL(string: "\(baseURL)/live-classes/\(liveClassId)/chat") else {
            completion(false, "Invalid URL")
            return
        }
        
        let parameters: [String: Any] = ["message": message]
        
        guard let jsonData = try? JSONSerialization.data(withJSONObject: parameters) else {
            completion(false, "Failed to encode message data")
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.httpBody = jsonData
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    print("Send chat message error: \(error.localizedDescription)")
                    completion(false, error.localizedDescription)
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, nil)
                } else {
                    var errorMessage = "Failed to send message"
                    if let data = data,
                       let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let message = json["message"] as? String {
                        errorMessage = message
                    }
                    
                    completion(false, errorMessage)
                }
            }
        }.resume()
    }
    
    func fetchLiveClassChatHistory(classId: Int, completion: @escaping (Bool, String?, [[String: Any]]?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/live-class-chat/\(classId)/history")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated", nil)
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)", nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let messages = jsonResponse["messages"] as? [[String: Any]] {
                        completion(true, "Chat history fetched successfully", messages)
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func sendLiveClassChatMessage(classId: Int, message: String, completion: @escaping (Bool, String?) -> Void) {
        let parameters: [String: Any] = ["message": message]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            completion(false, "Failed to encode message data")
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/live-class-chat/\(classId)/send")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated")
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, "Message sent successfully")
                } else {
                    completion(false, "Failed to send message")
                }
            }
        }.resume()
    }
    
    func joinLiveClass(classId: Int, completion: @escaping (Bool, String?, [String: Any]?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/live-classes/\(classId)/join")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated", nil)
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)", nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Join live class response: \(responseString)")
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        let message = jsonResponse["message"] as? String
                        
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            completion(true, message ?? "Joined class successfully", jsonResponse)
                        } else {
                            completion(false, message ?? "Failed to join class", nil)
                        }
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func sendSignal(classId: Int, signalData: [String: Any], completion: @escaping (Bool, String?) -> Void) {
        guard let postData = try? JSONSerialization.data(withJSONObject: signalData) else {
            completion(false, "Failed to encode signal data")
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/live-classes/\(classId)/signal")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated")
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, "Signal sent successfully")
                } else {
                    completion(false, "Failed to send signal")
                }
            }
        }.resume()
    }
    
    func sendIceCandidate(classId: Int, candidateData: [String: Any], completion: @escaping (Bool, String?) -> Void) {
        guard let postData = try? JSONSerialization.data(withJSONObject: candidateData) else {
            completion(false, "Failed to encode ICE candidate data")
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/live-classes/\(classId)/ice-candidate")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated")
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    completion(true, "ICE candidate sent successfully")
                } else {
                    completion(false, "Failed to send ICE candidate")
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Live Classes Functions
    // ======================================

    // MARK: - Courses Management
    // ======================================
    // Beginning of Courses Functions
    // ======================================
    
    func createCourse(title: String, description: String, price: String, topicId: String, contentType: String, coverImage: Data?, coverImageFileName: String, videoData: Data?, videoFileName: String, fileData: Data?, fileFileName: String, completion: @escaping (Bool, String?) -> Void) {
        // Multipart form data boundary
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Add text fields
        let textFields: [String: String] = [
            "title": title,
            "description": description,
            "price": price,
            "topic_id": topicId,
            "content_type": contentType
        ]
        
        for (key, value) in textFields {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"\(key)\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(value)\r\n".data(using: .utf8)!)
        }
        
        // Add cover image
        if let imageData = coverImage {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"image\"; filename=\"\(coverImageFileName)\"\r\n".data(using: .utf8)!)
            body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
            body.append(imageData)
            body.append("\r\n".data(using: .utf8)!)
        }
        
        // Add video
        if let videoData = videoData {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"video\"; filename=\"\(videoFileName)\"\r\n".data(using: .utf8)!)
            body.append("Content-Type: video/mp4\r\n\r\n".data(using: .utf8)!)
            body.append(videoData)
            body.append("\r\n".data(using: .utf8)!)
        }
        
        // Add additional file
        if let fileData = fileData {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"file\"; filename=\"\(fileFileName)\"\r\n".data(using: .utf8)!)
            body.append("Content-Type: application/octet-stream\r\n\r\n".data(using: .utf8)!)
            body.append(fileData)
            body.append("\r\n".data(using: .utf8)!)
        }
        
        // Final boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        // Set up the URL request
        guard let url = URL(string: "\(baseURL)/create-course") else {
            completion(false, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url, timeoutInterval: Double.infinity)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        
        // Show detailed log if in debug mode
#if DEBUG
        print("Creating course with title: \(title), price: \(price), topic: \(topicId)")
        print("Request URL: \(url.absoluteString)")
        print("Authorization: Bearer \(storedBearer)")
#endif
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                DispatchQueue.main.async {
                    completion(false, "View model was deallocated")
                }
                return
            }
            
            DispatchQueue.main.async {
                // Check for network error
                if let error = error {
                    print("Network error creating course: \(error.localizedDescription)")
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                // Check HTTP status code
                guard let httpResponse = response as? HTTPURLResponse else {
                    completion(false, "Invalid response")
                    return
                }
                
                // Log response for debugging
                if let data = data, let responseString = String(data: data, encoding: .utf8) {
                    print("Create course response: \(responseString)")
                }
                
                // Handle success/failure based on status code
                if httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    // Success - refresh courses
                    self.fetchCourses()
                    
                    // Try to extract success message
                    if let data = data {
                        do {
                            struct CourseResponse: Codable {
                                let message: String?
                                let success: Bool?
                            }
                            
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(CourseResponse.self, from: data)
                            completion(true, response.message ?? "Course created successfully")
                        } catch {
                            // If parsing fails, still consider it a success
                            completion(true, "Course created successfully")
                        }
                    } else {
                        completion(true, "Course created successfully")
                    }
                } else {
                    // API error
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        completion(false, "Course creation failed: \(responseString)")
                    } else {
                        completion(false, "Course creation failed with status code: \(httpResponse.statusCode)")
                    }
                }
            }
        }.resume()
    }
    
    func fetchCourses() {
        isLoadingCourses = true
        hasNoCoursesAvailable = false
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/courses-by-topic")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("access_token=\(storedBearer)", forHTTPHeaderField: "Cookie")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingCourses = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching courses: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Debug: Log the raw response
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Courses API response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    let coursesResponse = try decoder.decode(CoursesResponse.self, from: data)
                    
                    // Extract all courses from the topics
                    self.coursesByTopic = []
                    for topicWithCourses in coursesResponse.coursesByTopic {
                        self.coursesByTopic.append(contentsOf: topicWithCourses.courses)
                    }
                    
                    self.recommendedCourses = coursesResponse.recommendedCourses
                    
                    // Set flag if both arrays are empty
                    self.hasNoCoursesAvailable = self.coursesByTopic.isEmpty &&
                    self.recommendedCourses.isEmpty
                    
                    print("Successfully fetched courses from \(coursesResponse.coursesByTopic.count) topics and \(self.recommendedCourses.count) recommended courses")
                    
                } catch {
                    self.error = "Failed to decode courses response: \(error.localizedDescription)"
                    print("Courses decoding error: \(error)")
                    
                    // More detailed error info for debugging
                    if let decodingError = error as? DecodingError {
                        switch decodingError {
                        case .typeMismatch(let type, let context):
                            print("Type mismatch: \(type), context: \(context)")
                        case .valueNotFound(let type, let context):
                            print("Value not found: \(type), context: \(context)")
                        case .keyNotFound(let key, let context):
                            print("Key not found: \(key), context: \(context)")
                        case .dataCorrupted(let context):
                            print("Data corrupted: \(context)")
                        @unknown default:
                            print("Unknown decoding error")
                        }
                    }
                }
            }
        }.resume()
    }
    
    func enrollInCourse(courseId: Int, completion: @escaping (Bool, String?) -> Void) {
        let urlString = "\(baseURL)/courses/\(courseId)/enroll"
        
        guard let url = URL(string: urlString) else {
            completion(false, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                DispatchQueue.main.async {
                    completion(false, "View model was deallocated")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)")
                    return
                }
                
                guard let httpResponse = response as? HTTPURLResponse else {
                    completion(false, "Invalid response")
                    return
                }
                
                // Check for successful status code
                if httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    // Success - refresh the course list to update enrollment status
                    self.fetchCourses()
                    
                    // Parse response if available
                    if let data = data {
                        do {
                            struct EnrollmentResponse: Codable {
                                let message: String?
                                let success: Bool?
                            }
                            
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(EnrollmentResponse.self, from: data)
                            completion(true, response.message ?? "Successfully enrolled")
                        } catch {
                            // If parsing fails, still consider it a success
                            completion(true, "Successfully enrolled")
                        }
                    } else {
                        completion(true, "Successfully enrolled")
                    }
                } else {
                    // API error
                    if let data = data, let responseString = String(data: data, encoding: .utf8) {
                        completion(false, "Enrollment failed: \(responseString)")
                    } else {
                        completion(false, "Enrollment failed with status code: \(httpResponse.statusCode)")
                    }
                }
            }
        }.resume()
    }
    
    // Method to fetch a single course by ID for refreshing course details
    func fetchCourseById(courseId: Int, completion: @escaping (Course?, String?) -> Void) {
        let urlString = "\(baseURL)/courses/\(courseId)"
        
        guard let url = URL(string: urlString) else {
            completion(nil, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            DispatchQueue.main.async {
                if let error = error {
                    completion(nil, "Network error: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    completion(nil, "No data received")
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    
                    // Try to decode based on how your API returns a single course
                    // Option 1: If the API returns the course directly
                    if let course = try? decoder.decode(Course.self, from: data) {
                        completion(course, nil)
                        return
                    }
                    
                    // Option 2: If the API returns a wrapper object with a "course" key
                    struct CourseResponse: Codable {
                        let course: Course
                    }
                    
                    if let response = try? decoder.decode(CourseResponse.self, from: data) {
                        completion(response.course, nil)
                        return
                    }
                    
                    // If neither works, log the response for debugging
                    if let responseString = String(data: data, encoding: .utf8) {
                        print("Course detail API response: \(responseString)")
                    }
                    
                    completion(nil, "Failed to decode course data")
                } catch {
                    completion(nil, "Error decoding course: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func createCourseSection(courseId: Int, title: String, description: String, order: Int, completion: @escaping (Bool, String?, [String: Any]?) -> Void) {
        let parameters: [String: Any] = [
            "title": title,
            "description": description,
            "order": order
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            completion(false, "Failed to encode section data", nil)
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/courses/\(courseId)/sections")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated", nil)
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)", nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        let message = jsonResponse["message"] as? String
                        let section = jsonResponse["section"] as? [String: Any]
                        
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            completion(true, message ?? "Section created successfully", section)
                        } else {
                            completion(false, message ?? "Failed to create section", nil)
                        }
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func createLesson(sectionId: Int, title: String, content: String, contentType: String, duration: Int, order: Int, mediaData: Data? = nil, completion: @escaping (Bool, String?, [String: Any]?) -> Void) {
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Add text parameters
        let textParams: [String: Any] = [
            "title": title,
            "content": content,
            "content_type": contentType,
            "duration": duration,
            "order": order
        ]
        
        for (key, value) in textParams {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"\(key)\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(value)\r\n".data(using: .utf8)!)
        }
        
        // Add media file if provided
        if let mediaData = mediaData {
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"media_file\"; filename=\"lesson_media.mp4\"\r\n".data(using: .utf8)!)
            body.append("Content-Type: video/mp4\r\n\r\n".data(using: .utf8)!)
            body.append(mediaData)
            body.append("\r\n".data(using: .utf8)!)
        }
        
        // Add closing boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        var request = URLRequest(url: URL(string: "\(baseURL)/sections/\(sectionId)/lessons")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated", nil)
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)", nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        let message = jsonResponse["message"] as? String
                        let lesson = jsonResponse["lesson"] as? [String: Any]
                        
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            completion(true, message ?? "Lesson created successfully", lesson)
                        } else {
                            completion(false, message ?? "Failed to create lesson", nil)
                        }
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func markLessonComplete(lessonId: Int, completion: @escaping (Bool, String?, [String: Any]?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/lessons/\(lessonId)/complete")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self else {
                completion(false, "View model was deallocated", nil)
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    completion(false, "Network error: \(error.localizedDescription)", nil)
                    return
                }
                
                guard let data = data else {
                    completion(false, "No data received", nil)
                    return
                }
                
                do {
                    if let jsonResponse = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        let message = jsonResponse["message"] as? String
                        let progress = jsonResponse["progress"] as? [String: Any]
                        
                        if let httpResponse = response as? HTTPURLResponse,
                           httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                            completion(true, message ?? "Lesson marked as complete", progress)
                        } else {
                            completion(false, message ?? "Failed to mark lesson as complete", nil)
                        }
                    } else {
                        completion(false, "Invalid response format", nil)
                    }
                } catch {
                    completion(false, "Error parsing response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Courses Functions
    // ======================================

    // MARK: - Readlists Management
    // ======================================
    // Beginning of Readlists Functions
    // ======================================
    
    // Model for user readlists response
    struct UserReadlistsResponse: Codable {
        let success: Bool
        let readlists: [APIReadlist]
        let message: String?
    }
    
    // Internal API Readlist model - will be converted to the app's Readlist model
    struct APIReadlist: Codable {
        let id: String
        let title: String
        let description: String?
        let cover_image: String?
        let item_count: Int
        let created_by: String
        let created_at: String
    }
    
    // Fetch user's readlists
    func fetchUserReadlists(completion: @escaping (Bool, [Readlist]?, String?) -> Void) {
        let urlComponents = URLComponents(string: "\(baseURL)/readlists/user")!
        
        guard let url = urlComponents.url else {
            completion(false, nil, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                print("Error fetching user readlists: \(error.localizedDescription)")
                completion(false, nil, error.localizedDescription)
                return
            }
            
            guard let data = data else {
                completion(false, nil, "No data received")
                return
            }
            
            do {
                let decoder = JSONDecoder()
                let response: UserReadlistsResponse = try decoder.decode(UserReadlistsResponse.self, from: data)
                
                // Convert APIReadlist to Readlist
                let readlists = response.readlists.map { apiReadlist -> Readlist in
                    return Readlist(
                        id: apiReadlist.id,
                        title: apiReadlist.title,
                        description: apiReadlist.description,
                        coverImage: apiReadlist.cover_image,
                        itemCount: apiReadlist.item_count,
                        createdBy: apiReadlist.created_by,
                        createdAt: apiReadlist.created_at
                    )
                }
                
                completion(true, readlists, nil)
            } catch {
                print("Readlists decoding error: \(error)")
                completion(false, nil, "Failed to decode readlists response")
            }
        }.resume()
    }
    
    func removeReadlistItem(readlistId: Int, itemId: Int, completion: @escaping (Bool, String?) -> Void) {
          var request = URLRequest(url: URL(string: "\(baseURL)/readlists/\(readlistId)/items/\(itemId)")!)
          request.httpMethod = "DELETE"
          request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")

          URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
              guard let self = self, self.handleApiResponse(data, response, error) else {
                  DispatchQueue.main.async {
                      completion(false, "Authentication error or network issue")
                  }
                  return
              }

              DispatchQueue.main.async {
                  if let error = error {
                      completion(false, error.localizedDescription)
                      return
                  }

                  if let httpResponse = response as? HTTPURLResponse,
                     httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                      // Refresh readlist to update the UI
                      self.fetchReadlistDetail(readlistId: readlistId)
                      completion(true, "Item removed successfully")
                  } else {
                      completion(false, "Failed to remove item")
                  }
              }
          }.resume()
      }
    
    func fetchReadlistDetail(readlistId: Int) {
        isLoadingReadlistDetail = true
        error = nil
        
        print("Starting fetch for readlist ID: \(readlistId)")
        
        var request = URLRequest(url: URL(string: "\(baseURL)/readlists/\(readlistId)")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("access_token=\(storedBearer)", forHTTPHeaderField: "Cookie")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingReadlistDetail = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching readlist detail: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Debug: Log the raw response
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Readlist detail API response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ReadlistResponse.self, from: data)
                    self.currentReadlist = response.readlist
                    print("Successfully decoded readlist: \(response.readlist.title) with \(response.readlist.items.count) items")
                } catch {
                    self.error = "Failed to decode readlist detail: \(error.localizedDescription)"
                    print("Readlist detail decoding error: \(error)")
                    
                    // More detailed error info for debugging
                    if let decodingError = error as? DecodingError {
                        switch decodingError {
                        case .typeMismatch(let type, let context):
                            print("Type mismatch: \(type), context: \(context)")
                        case .valueNotFound(let type, let context):
                            print("Value not found: \(type), context: \(context)")
                        case .keyNotFound(let key, let context):
                            print("Key not found: \(key), context: \(context)")
                        case .dataCorrupted(let context):
                            print("Data corrupted: \(context)")
                        @unknown default:
                            print("Unknown decoding error")
                        }
                    }
                }
            }
        }.resume()
    }
    
    // Function to format date string for display
    func formatReadlistDate(_ dateString: String) -> String {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        
        guard let date = formatter.date(from: dateString) else {
            return "Unknown date"
        }
        
        let displayFormatter = DateFormatter()
        displayFormatter.dateStyle = .medium
        displayFormatter.timeStyle = .none
        
        return displayFormatter.string(from: date)
    }
    
    func shareReadlist(shareUrl: String) {
        // Create a URL to share
        guard let url = URL(string: shareUrl) else { return }
        
        // Create activity view controller with the URL
        let activityViewController = UIActivityViewController(
            activityItems: [url],
            applicationActivities: nil
        )
        
        // Present the view controller
        // Note: This requires access to a UIViewController to present from
        // You would typically call this from a view and pass the UIViewController
        
        // For now, we'll just print the share URL
        print("Share URL: \(shareUrl)")
    }
    
    // ======================================
    // End of Readlists Functions
    // ======================================

    // MARK: - Libraries Management
    // ======================================
    // Beginning of Libraries Functions
    // ======================================
    
    func fetchLibraries() {
        Task {
            await fetchLibrariesAsync()
        }
    }
    
    private func fetchLibrariesAsync() async {
        DispatchQueue.main.async {
            self.isLoadingLibraries = true
            self.error = nil
        }
        
        guard let url = URL(string: "\(baseURL)/libraries") else {
            await MainActor.run {
                self.error = "Invalid URL"
                self.isLoadingLibraries = false
            }
            return
        }
        
        var request = URLRequest(url: url, timeoutInterval: 30)
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("access_token=\(storedBearer)", forHTTPHeaderField: "Cookie")
        request.httpMethod = "GET"
        
        do {
            let (data, response) = try await URLSession.shared.data(for: request)
            
            // Check for unauthorized response (401)
            if let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode == 401 {
                await MainActor.run {
                    self.error = "Session expired. Please sign in again."
                    self.authManager.handleUnauthorizedResponse()
                    self.isLoadingLibraries = false
                }
                return
            }
            
            // Debug: Log the raw response
#if DEBUG
            if let responseString = String(data: data, encoding: .utf8) {
                print("Libraries API response: \(responseString)")
            }
#endif
            
            // Parse the response
            do {
                let decoder = JSONDecoder()
                let response = try decoder.decode(LibrariesResponse.self, from: data)
                
                await MainActor.run {
                    self.libraries = response.libraries
                    self.isLoadingLibraries = false
                    print("Successfully decoded \(response.libraries.count) libraries")
                }
            } catch {
                await handleDecodingError(error)
            }
        } catch {
            await MainActor.run {
                self.error = "Network error: \(error.localizedDescription)"
                self.isLoadingLibraries = false
                print("Error fetching libraries: \(error.localizedDescription)")
            }
        }
    }
    
    private func handleDecodingError(_ error: Error) async {
        await MainActor.run {
            self.error = "Failed to decode libraries response"
            self.isLoadingLibraries = false
            
            print("Libraries decoding error: \(error)")
            
            // More detailed error info for debugging
            if let decodingError = error as? DecodingError {
                switch decodingError {
                case .typeMismatch(let type, let context):
                    print("Type mismatch: \(type), context: \(context)")
                case .valueNotFound(let type, let context):
                    print("Value not found: \(type), context: \(context)")
                case .keyNotFound(let key, let context):
                    print("Key not found: \(key), context: \(context)")
                case .dataCorrupted(let context):
                    print("Data corrupted: \(context)")
                @unknown default:
                    print("Unknown decoding error")
                }
            }
        }
    }
    
    // Get library details
    func fetchLibraryDetails(libraryId: Int) {
        isLoadingLibraries = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/libraries/\(libraryId)")!,
                                 timeoutInterval: Double.infinity)
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("access_token=\(storedBearer)", forHTTPHeaderField: "Cookie")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingLibraries = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Debug: Log the raw response
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Library details API response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    let library = try decoder.decode(Library.self, from: data)
                    
                    // Update the library in the libraries array
                    if let index = self.libraries.firstIndex(where: { $0.id == library.id }) {
                        self.libraries[index] = library
                    } else {
                        // If not found, add it
                        self.libraries.append(library)
                    }
                } catch {
                    self.error = "Failed to decode library details: \(error.localizedDescription)"
                    print("Library details decoding error: \(error)")
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Libraries Functions
    // ======================================

    // MARK: - Messaging & Conversations
    // ======================================
    // Beginning of Messaging & Conversations Functions
    // ======================================
    
    func fetchConversations() {
        isLoadingConversations = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/conversations")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            // Validate the API response using a helper
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingConversations = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Print raw response string for debugging purposes
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Conversations raw response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    
                    // Inline wrapper to match the JSON structure.
                    struct ConversationsResponse: Codable {
                        let conversations: [Conversation]
                    }
                    
                    let response = try decoder.decode(ConversationsResponse.self, from: data)
                    self.conversations = response.conversations
                    print("Successfully decoded \(response.conversations.count) conversations")
                    
                    // Update the currently selected conversation if needed.
                    if let currentConvId = self.currentConversation?.id,
                       let updatedConv = response.conversations.first(where: { $0.id == currentConvId }) {
                        self.currentConversation = updatedConv
                    }
                } catch {
                    self.error = "Failed to decode conversations response: \(error.localizedDescription)"
                    print("Conversations decoding error: \(error)")
                    
                    // Fallback for handling partial or malformed JSON.
                    if let jsonDict = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let conversationsArray = jsonDict["conversations"] as? [[String: Any]] {
                        print("Attempting manual parse of \(conversationsArray.count) conversations")
                        self.parseFallbackConversations(conversationsArray)
                    }
                }
            }
        }.resume()
    }
    
    private func parseFallbackConversations(_ conversationsArray: [[String: Any]]) {
        var parsedConversations: [Conversation] = []
        
        for conversationDict in conversationsArray {
            do {
                // Convert back to JSON and try to decode a single conversation
                let conversationData = try JSONSerialization.data(withJSONObject: conversationDict)
                if let conversation = try? JSONDecoder().decode(Conversation.self, from: conversationData) {
                    parsedConversations.append(conversation)
                }
            } catch {
                print("Error parsing individual conversation: \(error)")
            }
        }
        
        if !parsedConversations.isEmpty {
            self.conversations = parsedConversations
            print("Successfully parsed \(parsedConversations.count) conversations using fallback")
        }
    }
    
    func fetchConversationMessages(conversationId: String) {
        isLoadingComments = true  // Reusing this loading state
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/conversations/\(conversationId)")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingComments = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching conversation messages: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Print raw response for debugging
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Conversation messages raw response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    
                    // This structure matches the example JSON response
                    struct ConversationDetailResponse: Codable {
                        let conversation: Conversation
                    }
                    
                    let response = try decoder.decode(ConversationDetailResponse.self, from: data)
                    
                    // Update the current conversation
                    self.currentConversation = response.conversation
                    
                    // Update the messages list
                    self.currentConversationMessages = response.conversation.messages ?? []
                    
                    // Make sure to sort messages by creation date if needed
                    self.currentConversationMessages.sort {
                        $0.createdAt < $1.createdAt
                    }
                    
                    // Mark as read on the server by calling the markAsRead endpoint
                    self.markConversationAsRead(conversationId)
                    
                    print("Successfully loaded \(self.currentConversationMessages.count) messages for conversation \(conversationId)")
                } catch {
                    self.error = "Failed to decode conversation messages: \(error.localizedDescription)"
                    print("Conversation messages decoding error: \(error)")
                    
                    // Print more detailed error info for debugging
                    if let decodingError = error as? DecodingError {
                        switch decodingError {
                        case .typeMismatch(let type, let context):
                            print("Type mismatch: \(type), context: \(context)")
                        case .valueNotFound(let type, let context):
                            print("Value not found: \(type), context: \(context)")
                        case .keyNotFound(let key, let context):
                            print("Key not found: \(key), context: \(context)")
                        case .dataCorrupted(let context):
                            print("Data corrupted: \(context)")
                        @unknown default:
                            print("Unknown decoding error")
                        }
                    }
                }
            }
        }.resume()
    }
    
    private func markConversationAsRead(_ conversationId: String) {
        var request = URLRequest(url: URL(string: "\(baseURL)/conversations/\(conversationId)/read")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { _, _, _ in
            // We don't need to do anything with the response
        }.resume()
    }
    
    func sendMessage(username: String, message: String) {
        isLoadingSendMessage = true
        error = nil
        
        // Use JSON format instead of form-data
        let parameters: [String: Any] = [
            "username": username,
            "message": message
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingSendMessage = false
                self.error = "Failed to encode message data"
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/messages")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingSendMessage = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Print the raw response for debugging
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Send message response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(SendMessageResponse.self, from: data)
                    
                    // Add the new message to current conversation messages for immediate UI update
                    self.currentConversationMessages.append(response.message)
                    
                    // Update or set current conversation if needed
                    if self.currentConversation?.id == response.conversation.id {
                        // Update existing conversation
                        if self.currentConversation?.messages == nil {
                            self.currentConversation?.messages = []
                        }
                        self.currentConversation?.messages?.append(response.message)
                    } else {
                        // Set new conversation
                        self.currentConversation = Conversation(
                            id: response.conversation.id,
                            conversationId: response.conversation.conversationId,
                            userOneId: self.userId,
                            userTwoId: "",  // We don't know this from the response
                            lastMessageAt: response.message.createdAt,
                            createdAt: response.message.createdAt,
                            updatedAt: response.message.createdAt,
                            otherUser: response.conversation.otherUser,
                            unreadCount: 0,
                            latestMessage: response.message,
                            messages: [response.message]
                        )
                    }
                    
                    // Refresh conversations to get the updated list
                    self.fetchConversations()
                } catch {
                    self.error = "Failed to decode send message response: \(error.localizedDescription)"
                    print("Send message decoding error: \(error)")
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Messaging & Conversations Functions
    // ======================================

    // MARK: - Channels Management
    // ======================================
    // Beginning of Channels Functions
    // ======================================
    
    func fetchChannels() {
        isLoadingChannels = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingChannels = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching channels: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Debug: Log the raw response
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Channels response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelsResponse.self, from: data)
                    self.channels = response.channels
                    print("Successfully decoded \(response.channels.count) channels")
                } catch {
                    self.error = "Failed to decode channels response: \(error.localizedDescription)"
                    print("Channels decoding error: \(error)")
                    
                    // More detailed error info for debugging
                    if let decodingError = error as? DecodingError {
                        switch decodingError {
                        case .typeMismatch(let type, let context):
                            print("Type mismatch: \(type), context: \(context)")
                        case .valueNotFound(let type, let context):
                            print("Value not found: \(type), context: \(context)")
                        case .keyNotFound(let key, let context):
                            print("Key not found: \(key), context: \(context)")
                        case .dataCorrupted(let context):
                            print("Data corrupted: \(context)")
                        @unknown default:
                            print("Unknown decoding error")
                        }
                    }
                }
            }
        }.resume()
    }
    
    func fetchChannel(channelId: String) {
        isLoadingChannel = true
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingChannel = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching channel: \(error.localizedDescription)")
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                // Debug: Log the raw response
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Channel detail response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelDetailResponse.self, from: data)
                    self.currentChannel = response.channel
                    self.channelMessages = response.channel.messages ?? []
                    self.channelMembers = response.channel.members ?? []
                    print("Successfully decoded channel with \(self.channelMessages.count) messages and \(self.channelMembers.count) members")
                    
                } catch {
                    self.error = "Failed to decode channel detail: \(error.localizedDescription)"
                    print("Channel detail decoding error: \(error)")
                }
            }
        }.resume()
    }
    
    func createChannel(title: String, description: String? = nil, maxMembers: Int = 10, completion: @escaping (Bool, String?, Channel?) -> Void) {
        error = nil
        
        var parameters: [String: Any] = [
            "title": title,
            "max_members": maxMembers
        ]
        
        if let description = description {
            parameters["description"] = description
        }
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.error = "Failed to encode channel data"
                completion(false, "Failed to encode channel data", nil)
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue", nil)
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription, nil)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received", nil)
                    return
                }
                
                // Debug: Log the raw response
                if let responseString = String(data: data, encoding: .utf8) {
                    print("Create channel response: \(responseString)")
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelResponse.self, from: data)
                    
                    if let channel = response.channel {
                        // Add the new channel to our list
                        self.channels.insert(channel, at: 0)
                        completion(true, "Channel created successfully", channel)
                    } else {
                        completion(false, response.message ?? "Failed to create channel", nil)
                    }
                } catch {
                    self.error = "Failed to decode create channel response: \(error.localizedDescription)"
                    print("Create channel decoding error: \(error)")
                    completion(false, "Failed to decode response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func updateChannel(channelId: String, title: String? = nil, description: String? = nil, maxMembers: Int? = nil, completion: @escaping (Bool, String?) -> Void) {
        error = nil
        
        var parameters: [String: Any] = [:]
        
        if let title = title {
            parameters["title"] = title
        }
        
        if let description = description {
            parameters["description"] = description
        }
        
        if let maxMembers = maxMembers {
            parameters["max_members"] = maxMembers
        }
        
        guard !parameters.isEmpty else {
            completion(false, "No parameters to update")
            return
        }
        
        guard let putData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.error = "Failed to encode update data"
                completion(false, "Failed to encode update data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)")!)
        request.httpMethod = "PUT"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = putData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received")
                    return
                }
                
                // Check HTTP status code
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    do {
                        let decoder = JSONDecoder()
                        let response = try decoder.decode(ChannelResponse.self, from: data)
                        
                        if let updatedChannel = response.channel {
                            // Update the channel in our list
                            if let index = self.channels.firstIndex(where: { $0.id == channelId }) {
                                self.channels[index] = updatedChannel
                            }
                            
                            // Update current channel if it's the one we're viewing
                            if self.currentChannel?.id == channelId {
                                self.currentChannel = updatedChannel
                            }
                            
                            completion(true, response.message ?? "Channel updated successfully")
                        } else {
                            completion(true, response.message ?? "Channel updated successfully")
                        }
                    } catch {
                        // Still consider it a success based on the HTTP status code
                        completion(true, "Channel updated successfully")
                    }
                } else {
                    // Try to extract error message
                    do {
                        let decoder = JSONDecoder()
                        let response = try decoder.decode(ChannelResponse.self, from: data)
                        self.error = response.message
                        completion(false, response.message ?? "Failed to update channel")
                    } catch {
                        self.error = "Failed to update channel"
                        completion(false, "Failed to update channel")
                    }
                }
            }
        }.resume()
    }
    
    func deleteChannel(channelId: String, completion: @escaping (Bool, String?) -> Void) {
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)")!)
        request.httpMethod = "DELETE"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check HTTP status code
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    // Remove the channel from our list
                    self.channels.removeAll(where: { $0.id == channelId })
                    
                    // Clear current channel if it's the one we deleted
                    if self.currentChannel?.id == channelId {
                        self.currentChannel = nil
                        self.channelMessages = []
                        self.channelMembers = []
                    }
                    
                    completion(true, "Channel deleted successfully")
                } else {
                    // Try to extract error message from response
                    if let data = data {
                        do {
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(ErrorResponse.self, from: data)
                            self.error = response.message
                            completion(false, response.message ?? "Failed to delete channel")
                        } catch {
                            self.error = "Failed to delete channel"
                            completion(false, "Failed to delete channel")
                        }
                    } else {
                        self.error = "Failed to delete channel"
                        completion(false, "Failed to delete channel")
                    }
                }
            }
        }.resume()
    }
    
    func sendChannelMessage(channelId: String, message: String, attachment: Data? = nil, completion: @escaping (Bool, String?, ChannelMessage?) -> Void) {
        isLoadingSendChannelMessage = true
        error = nil
        
        // Create a multipart request if there's an attachment, otherwise use JSON
        if let attachment = attachment {
            sendChannelMessageWithAttachment(channelId: channelId, message: message, attachment: attachment, completion: completion)
        } else {
            // Simple JSON request for text-only messages
            let parameters: [String: Any] = ["message": message]
            
            guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
                DispatchQueue.main.async {
                    self.isLoadingSendChannelMessage = false
                    self.error = "Failed to encode message data"
                    completion(false, "Failed to encode message data", nil)
                }
                return
            }
            
            var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/message")!)
            request.httpMethod = "POST"
            request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
            request.addValue("application/json", forHTTPHeaderField: "Content-Type")
            request.httpBody = postData
            
            URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                guard let self = self, self.handleApiResponse(data, response, error) else {
                    DispatchQueue.main.async {
                        completion(false, "Authentication error or network issue", nil)
                    }
                    return
                }
                
                DispatchQueue.main.async {
                    self.isLoadingSendChannelMessage = false
                    
                    if let error = error {
                        self.error = error.localizedDescription
                        completion(false, error.localizedDescription, nil)
                        return
                    }
                    
                    guard let data = data else {
                        self.error = "No data received"
                        completion(false, "No data received", nil)
                        return
                    }
                    
                    do {
                        let decoder = JSONDecoder()
                        let response = try decoder.decode(ChannelMessageResponse.self, from: data)
                        
                        if let message = response.message {
                            // Add the message to our local messages list
                            self.channelMessages.append(message)
                            completion(true, "Message sent successfully", message)
                        } else {
                            self.error = "Failed to send message"
                            completion(false, "Failed to send message", nil)
                        }
                    } catch {
                        self.error = "Failed to decode message response: \(error.localizedDescription)"
                        print("Send channel message decoding error: \(error)")
                        completion(false, "Failed to decode response: \(error.localizedDescription)", nil)
                    }
                }
            }.resume()
        }
    }
    
    private func sendChannelMessageWithAttachment(channelId: String, message: String, attachment: Data, completion: @escaping (Bool, String?, ChannelMessage?) -> Void) {
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Add message part
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"message\"\r\n\r\n".data(using: .utf8)!)
        body.append("\(message)\r\n".data(using: .utf8)!)
        
        // Add attachment part
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"attachment\"; filename=\"attachment.jpg\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
        body.append(attachment)
        body.append("\r\n".data(using: .utf8)!)
        
        // Add closing boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/message")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue", nil)
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingSendChannelMessage = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription, nil)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received", nil)
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelMessageResponse.self, from: data)
                    
                    if let message = response.message {
                        // Add the message to our local messages list
                        self.channelMessages.append(message)
                        completion(true, "Message with attachment sent successfully", message)
                    } else {
                        self.error = "Failed to send message with attachment"
                        completion(false, "Failed to send message with attachment", nil)
                    }
                } catch {
                    self.error = "Failed to decode message response: \(error.localizedDescription)"
                    print("Send channel message with attachment decoding error: \(error)")
                    completion(false, "Failed to decode response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func addChannelMember(channelId: String, userId: String, role: String = "member", completion: @escaping (Bool, String?, ChannelMember?) -> Void) {
        error = nil
        
        let parameters: [String: Any] = [
            "user_id": userId,
            "role": role
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.error = "Failed to encode member data"
                completion(false, "Failed to encode member data", nil)
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/members")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue", nil)
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription, nil)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received", nil)
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelMemberResponse.self, from: data)
                    
                    if let member = response.member {
                        // Add the member to our local members list
                        self.channelMembers.append(member)
                        completion(true, "Member added successfully", member)
                    } else {
                        self.error = response.message ?? "Failed to add member"
                        completion(false, response.message ?? "Failed to add member", nil)
                    }
                } catch {
                    self.error = "Failed to decode member response: \(error.localizedDescription)"
                    print("Add member decoding error: \(error)")
                    completion(false, "Failed to decode response: \(error.localizedDescription)", nil)
                }
            }
        }.resume()
    }
    
    func removeChannelMember(channelId: String, userId: String, completion: @escaping (Bool, String?) -> Void) {
        error = nil
        
        let parameters: [String: Any] = ["user_id": userId]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.error = "Failed to encode member data"
                completion(false, "Failed to encode member data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/members")!)
        request.httpMethod = "DELETE"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check HTTP status code
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    completion(true, "Member removed successfully")
                } else {
                    // Try to extract error message from response
                    if let data = data {
                        do {
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(ErrorResponse.self, from: data)
                            self.error = response.message
                            completion(false, response.message ?? "Failed to remove member")
                        } catch {
                            self.error = "Failed to remove member"
                            completion(false, "Failed to remove member")
                        }
                    } else {
                        self.error = "Failed to remove member"
                        completion(false, "Failed to remove member")
                    }
                }
            }
        }.resume()
    }
    
    func updateChannelMemberRole(channelId: String, userId: String, role: String, completion: @escaping (Bool, String?) -> Void) {
        error = nil
        
        let parameters: [String: Any] = [
            "user_id": userId,
            "role": role
        ]
        
        guard let putData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.error = "Failed to encode role data"
                completion(false, "Failed to encode role data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/members/role")!)
        request.httpMethod = "PUT"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = putData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received")
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelMemberResponse.self, from: data)
                    
                    if let updatedMember = response.member {
                        // Update the member in our local list
                        completion(true, "Member role updated successfully")
                    } else {
                        self.error = response.message ?? "Failed to update member role"
                        completion(false, response.message ?? "Failed to update member role")
                    }
                } catch {
                    self.error = "Failed to decode member response: \(error.localizedDescription)"
                    print("Update member role decoding error: \(error)")
                    completion(false, "Failed to decode response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func leaveChannel(channelId: String, completion: @escaping (Bool, String?) -> Void) {
        error = nil
         
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/leave")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check HTTP status code
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    // Remove the channel from our list
                    self.channels.removeAll(where: { $0.id == channelId })
                    
                    // Clear current channel if it was the one left
                    if self.currentChannel?.id == channelId {
                        self.currentChannel = nil
                        self.channelMessages = []
                        self.channelMembers = []
                    }
                    
                    completion(true, "Successfully left the channel")
                } else {
                    // Try to extract error message from response
                    if let data = data {
                        do {
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(ErrorResponse.self, from: data)
                            self.error = response.message
                            completion(false, response.message ?? "Failed to leave channel")
                        } catch {
                            self.error = "Failed to leave channel"
                            completion(false, "Failed to leave channel")
                        }
                    } else {
                        self.error = "Failed to leave channel"
                        completion(false, "Failed to leave channel")
                    }
                }
            }
        }.resume()
    }
    
    func getChannelShareLink(channelId: String, completion: @escaping (Bool, String?) -> Void) {
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/share-link")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received")
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ShareLinkResponse.self, from: data)
                    self.channelShareLink = response.share_link
                    completion(true, response.share_link)
                } catch {
                    self.error = "Failed to decode share link response: \(error.localizedDescription)"
                    print("Share link decoding error: \(error)")
                    completion(false, "Failed to get share link")
                }
            }
        }.resume()
    }
    
    func fetchChannelsWithPagination(page: Int = 1, perPage: Int = 20, completion: @escaping (Bool, String?) -> Void) {
        isLoadingChannels = true
        error = nil
        
        var urlComponents = URLComponents(string: "\(baseURL)/channels")!
        urlComponents.queryItems = [
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)")
        ]
        
        guard let url = urlComponents.url else {
            DispatchQueue.main.async {
                self.isLoadingChannels = false
                self.error = "Invalid URL"
                completion(false, "Invalid URL")
            }
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingChannels = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching channels: \(error.localizedDescription)")
                    completion(false, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received")
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelsResponse.self, from: data)
                    
                    if page == 1 {
                        // First page - replace existing channels
                        self.channels = response.channels
                    } else {
                        // Subsequent page - append to existing channels
                        self.channels.append(contentsOf: response.channels)
                    }
                    
                    print("Successfully loaded \(response.channels.count) channels")
                    completion(true, nil)
                } catch {
                    self.error = "Failed to decode channels response: \(error.localizedDescription)"
                    print("Channels decoding error: \(error)")
                    completion(false, "Failed to parse channel data")
                }
            }
        }.resume()
    }
    
    func fetchChannelMembers(channelId: String, completion: @escaping (Bool, String?) -> Void) {
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/members")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching channel members: \(error.localizedDescription)")
                    completion(false, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received")
                    return
                }
                
                do {
                    struct MembersResponse: Codable {
                        let members: [ChannelMember]
                    }
                    
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(MembersResponse.self, from: data)
                    self.channelMembers = response.members
                    completion(true, nil)
                } catch {
                    self.error = "Failed to decode channel members: \(error.localizedDescription)"
                    print("Channel members decoding error: \(error)")
                    completion(false, "Failed to parse member data")
                }
            }
        }.resume()
    }
    func fetchChannelMessages(channelId: String, page: Int = 1, perPage: Int = 50, completion: @escaping (Bool, String?) -> Void) {
        isLoadingChannelMessages = true
        error = nil
        
        var urlComponents = URLComponents(string: "\(baseURL)/channels/\(channelId)/messages")!
        urlComponents.queryItems = [
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)")
        ]
        
        guard let url = urlComponents.url else {
            DispatchQueue.main.async {
                self.isLoadingChannelMessages = false
                self.error = "Invalid URL"
                completion(false, "Invalid URL")
            }
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingChannelMessages = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    print("Error fetching channel messages: \(error.localizedDescription)")
                    completion(false, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received")
                    return
                }
                
                do {
                    struct MessagesResponse: Codable {
                        let messages: [ChannelMessage]
                    }
                    
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(MessagesResponse.self, from: data)
                    
                    if page == 1 {
                        // First page - replace existing messages
                        self.channelMessages = response.messages
                    } else {
                        // Subsequent page - append to existing messages
                        self.channelMessages.append(contentsOf: response.messages)
                    }
                    
                    // Sort messages by creation time
                    self.channelMessages.sort { $0.created_at < $1.created_at }
                    
                    completion(true, nil)
                } catch {
                    self.error = "Failed to decode channel messages: \(error.localizedDescription)"
                    print("Channel messages decoding error: \(error)")
                    completion(false, "Failed to parse message data")
                }
            }
        }.resume()
    }
    
    func joinChannel(shareLink: String, completion: @escaping (Bool, String?, Channel?) -> Void) {
        isJoiningChannel = true
        error = nil
        
        let parameters: [String: Any] = ["share_link": shareLink]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isJoiningChannel = false
                self.error = "Failed to encode join data"
                completion(false, "Failed to encode join data", nil)
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/join")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue", nil)
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isJoiningChannel = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription, nil)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received", nil)
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelResponse.self, from: data)
                    
                    if let channel = response.channel {
                        // Add the channel to our list if it's not already there
                        if !self.channels.contains(where: { $0.id == channel.id }) {
                            self.channels.insert(channel, at: 0)
                        }
                        completion(true, "Successfully joined channel", channel)
                    } else {
                        completion(false, response.message ?? "Failed to join channel", nil)
                    }
                } catch {
                    self.error = "Failed to decode join response: \(error.localizedDescription)"
                    print("Join channel decoding error: \(error)")
                    
                    if let responseString = String(data: data, encoding: .utf8) {
                        print("Raw join channel response: \(responseString)")
                    }
                    
                    completion(false, "Failed to parse join response", nil)
                }
            }
        }.resume()
    }
    
    func markChannelMessagesAsRead(channelId: String, completion: @escaping (Bool, String?) -> Void) {
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/read")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    // Update unread count in the local data
                    if let index = self.channels.firstIndex(where: { $0.id == channelId }) {
                        self.channels[index].unread_count = 0
                    }
                    
                    if self.currentChannel?.id == channelId {
                        self.currentChannel?.unread_count = 0
                    }
                    
                    completion(true, "Messages marked as read")
                } else {
                    completion(false, "Failed to mark messages as read")
                }
            }
        }.resume()
    }
    
    func searchChannels(query: String, completion: @escaping (Bool, String?, [Channel]?) -> Void) {
        error = nil
        
        var urlComponents = URLComponents(string: "\(baseURL)/channels/search")!
        urlComponents.queryItems = [URLQueryItem(name: "query", value: query)]
        
        guard let url = urlComponents.url else {
            DispatchQueue.main.async {
                self.error = "Invalid URL"
                completion(false, "Invalid URL", nil)
            }
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Accept")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue", nil)
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription, nil)
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    completion(false, "No data received", nil)
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response = try decoder.decode(ChannelsResponse.self, from: data)
                    completion(true, "Search completed", response.channels)
                } catch {
                    self.error = "Failed to decode search response: \(error.localizedDescription)"
                    print("Search channels decoding error: \(error)")
                    completion(false, "Failed to parse search results", nil)
                }
            }
        }.resume()
    }
    
    func sendChannelMessageWithReply(channelId: String, message: String, replyToId: String, attachment: Data? = nil, completion: @escaping (Bool, String?, ChannelMessage?) -> Void) {
        isLoadingSendChannelMessage = true
        error = nil
        
        if let attachment = attachment {
            // Handle multipart form with attachment
            let boundary = "Boundary-\(UUID().uuidString)"
            var body = Data()
            
            // Add message part
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"message\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(message)\r\n".data(using: .utf8)!)
            
            // Add reply_to part
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"reply_to\"\r\n\r\n".data(using: .utf8)!)
            body.append("\(replyToId)\r\n".data(using: .utf8)!)
            
            // Add attachment part
            body.append("--\(boundary)\r\n".data(using: .utf8)!)
            body.append("Content-Disposition: form-data; name=\"attachment\"; filename=\"attachment.jpg\"\r\n".data(using: .utf8)!)
            body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
            body.append(attachment)
            body.append("\r\n".data(using: .utf8)!)
            
            // Add closing boundary
            body.append("--\(boundary)--\r\n".data(using: .utf8)!)
            
            var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/message")!)
            request.httpMethod = "POST"
            request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
            request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
            request.httpBody = body
            
            URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                guard let self = self, self.handleApiResponse(data, response, error) else {
                    DispatchQueue.main.async {
                        completion(false, "Authentication error or network issue", nil)
                    }
                    return
                }
                
                DispatchQueue.main.async {
                    self.isLoadingSendChannelMessage = false
                    
                    if let error = error {
                        self.error = error.localizedDescription
                        completion(false, error.localizedDescription, nil)
                        return
                    }
                    
                    guard let data = data else {
                        self.error = "No data received"
                        completion(false, "No data received", nil)
                        return
                    }
                    
                    do {
                        let decoder = JSONDecoder()
                        let response = try decoder.decode(ChannelMessageResponse.self, from: data)
                        
                        if let message = response.message {
                            // Add the message to our local messages list
                            self.channelMessages.append(message)
                            completion(true, "Message with attachment sent successfully", message)
                        } else {
                            self.error = "Failed to send message with attachment"
                            completion(false, "Failed to send message with attachment", nil)
                        }
                    } catch {
                        self.error = "Failed to decode message response: \(error.localizedDescription)"
                        print("Send channel message with attachment decoding error: \(error)")
                        completion(false, "Failed to decode response: \(error.localizedDescription)", nil)
                    }
                }
            }.resume()
        } else {
            // Simple JSON request for text-only messages
            let parameters: [String: Any] = [
                "message": message,
                "reply_to": replyToId
            ]
            
            guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
                DispatchQueue.main.async {
                    self.isLoadingSendChannelMessage = false
                    self.error = "Failed to encode message data"
                    completion(false, "Failed to encode message data", nil)
                }
                return
            }
            
            var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/message")!)
            request.httpMethod = "POST"
            request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
            request.addValue("application/json", forHTTPHeaderField: "Content-Type")
            request.httpBody = postData
            
            URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
                guard let self = self, self.handleApiResponse(data, response, error) else {
                    DispatchQueue.main.async {
                        completion(false, "Authentication error or network issue", nil)
                    }
                    return
                }
                
                DispatchQueue.main.async {
                    self.isLoadingSendChannelMessage = false
                    
                    if let error = error {
                        self.error = error.localizedDescription
                        completion(false, error.localizedDescription, nil)
                        return
                    }
                    
                    guard let data = data else {
                        self.error = "No data received"
                        completion(false, "No data received", nil)
                        return
                    }
                    
                    do {
                        let decoder = JSONDecoder()
                        let response = try decoder.decode(ChannelMessageResponse.self, from: data)
                        
                        if let message = response.message {
                            // Add the message to our local messages list
                            self.channelMessages.append(message)
                            completion(true, "Message sent successfully", message)
                        } else {
                            self.error = "Failed to send message"
                            completion(false, "Failed to send message", nil)
                        }
                    } catch {
                        self.error = "Failed to decode message response: \(error.localizedDescription)"
                        print("Send channel message decoding error: \(error)")
                        completion(false, "Failed to decode response: \(error.localizedDescription)", nil)
                    }
                }
            }.resume()
        }
    }
    
    func deleteChannelMessage(channelId: String, messageId: String, completion: @escaping (Bool, String?) -> Void) {
        error = nil
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/messages/\(messageId)")!)
        request.httpMethod = "DELETE"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check HTTP status code
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    // Remove the message from our local list
                    self.channelMessages.removeAll(where: { $0.id == messageId })
                    
                    completion(true, "Message deleted successfully")
                } else {
                    // Try to extract error message
                    if let data = data {
                        do {
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(ErrorResponse.self, from: data)
                            self.error = response.message
                            completion(false, response.message ?? "Failed to delete message")
                        } catch {
                            self.error = "Failed to delete message"
                            completion(false, "Failed to delete message")
                        }
                    } else {
                        self.error = "Failed to delete message"
                        completion(false, "Failed to delete message")
                    }
                }
            }
        }.resume()
    }
    func updateChannelImage(channelId: String, imageData: Data, completion: @escaping (Bool, String?) -> Void) {
        let boundary = "Boundary-\(UUID().uuidString)"
        var body = Data()
        
        // Add image part
        body.append("--\(boundary)\r\n".data(using: .utf8)!)
        body.append("Content-Disposition: form-data; name=\"image\"; filename=\"channel_image.jpg\"\r\n".data(using: .utf8)!)
        body.append("Content-Type: image/jpeg\r\n\r\n".data(using: .utf8)!)
        body.append(imageData)
        body.append("\r\n".data(using: .utf8)!)
        
        // Add closing boundary
        body.append("--\(boundary)--\r\n".data(using: .utf8)!)
        
        var request = URLRequest(url: URL(string: "\(baseURL)/channels/\(channelId)/image")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                if let error = error {
                    self.error = error.localizedDescription
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Check HTTP status code
                if let httpResponse = response as? HTTPURLResponse,
                   httpResponse.statusCode >= 200 && httpResponse.statusCode < 300 {
                    
                    // Refresh channel details to get updated image
                    self.fetchChannel(channelId: channelId)
                    
                    completion(true, "Channel image updated successfully")
                } else {
                    // Try to extract error message
                    if let data = data {
                        do {
                            let decoder = JSONDecoder()
                            let response = try decoder.decode(ErrorResponse.self, from: data)
                            self.error = response.message
                            completion(false, response.message ?? "Failed to update channel image")
                        } catch {
                            self.error = "Failed to update channel image"
                            completion(false, "Failed to update channel image")
                        }
                    } else {
                        self.error = "Failed to update channel image"
                        completion(false, "Failed to update channel image")
                    }
                }
            }
        }.resume()
    }
    
    func processChannelEvent(_ eventData: [String: Any]) {
        guard let eventType = eventData["event"] as? String else { return }
        
        switch eventType {
        case "ChannelMessageSent":
            if let messageData = eventData["message"] as? [String: Any],
               let channelId = messageData["channel_id"] as? String {
                
                // Only process if this is for the current channel
                if currentChannel?.id == channelId {
                    if let jsonData = try? JSONSerialization.data(withJSONObject: messageData),
                       let message = try? JSONDecoder().decode(ChannelMessage.self, from: jsonData) {
                        
                        // Add the new message to our list
                        DispatchQueue.main.async {
                            self.channelMessages.append(message)
                        }
                    }
                }
                
                // Update unread count for the channel if it's not the current one
                if currentChannel?.id != channelId {
                    DispatchQueue.main.async {
                        if let index = self.channels.firstIndex(where: { $0.id == channelId }) {
                            self.channels[index].unread_count = (self.channels[index].unread_count ?? 0) + 1
                        }
                    }
                }
            }
            
        case "ChannelMemberJoined":
            if let memberData = eventData["member"] as? [String: Any],
               let channelId = memberData["channel_id"] as? String {
                
                // Only process if this is for the current channel
                if currentChannel?.id == channelId {
                    if let jsonData = try? JSONSerialization.data(withJSONObject: memberData),
                       let member = try? JSONDecoder().decode(ChannelMember.self, from: jsonData) {
                        
                        // Add the new member to our list
                        DispatchQueue.main.async {
                            self.channelMembers.append(member)
                        }
                    }
                }
                
                // Refresh the channel if needed
                if currentChannel?.id == channelId {
                    DispatchQueue.main.async {
                        self.fetchChannel(channelId: channelId)
                    }
                }
            }
            
        case "ChannelMemberLeft", "ChannelMemberRemoved":
            if let memberData = eventData["member"] as? [String: Any],
               let channelId = memberData["channel_id"] as? String,
               let userId = memberData["user_id"] as? String {
                
                // Only process if this is for the current channel
                if currentChannel?.id == channelId {
                    // Remove the member from our list
                    DispatchQueue.main.async {
                        self.channelMembers.removeAll(where: { $0.user_id == userId })
                    }
                }
                
                // If the current user was removed, update the channels list
                if userId == self.userId {
                    DispatchQueue.main.async {
                        self.channels.removeAll(where: { $0.id == channelId })
                        
                        // If the user was viewing this channel, clear it
                        if self.currentChannel?.id == channelId {
                            self.currentChannel = nil
                            self.channelMessages = []
                            self.channelMembers = []
                        }
                    }
                }
            }
            
        case "ChannelUpdated":
            if let channelData = eventData["channel"] as? [String: Any],
               let channelId = channelData["id"] as? String {
                
                if let jsonData = try? JSONSerialization.data(withJSONObject: channelData),
                   let updatedChannel = try? JSONDecoder().decode(Channel.self, from: jsonData) {
                    
                    DispatchQueue.main.async {
                        // Update in channels list
                        if let index = self.channels.firstIndex(where: { $0.id == channelId }) {
                            self.channels[index] = updatedChannel
                        }
                        
                        // Update current channel if viewing
                        if self.currentChannel?.id == channelId {
                            self.currentChannel = updatedChannel
                        }
                    }
                }
            }
            
        case "ChannelDeleted":
            if let channelId = eventData["channel_id"] as? String {
                DispatchQueue.main.async {
                    // Remove from channels list
                    self.channels.removeAll(where: { $0.id == channelId })
                    
                    // Clear current channel if viewing
                    if self.currentChannel?.id == channelId {
                        self.currentChannel = nil
                        self.channelMessages = []
                        self.channelMembers = []
                    }
                }
            }
            
        default:
            print("Unhandled channel event type: \(eventType)")
        }
    }
    
    
    // ======================================
    // End of Channels Functions
    // ======================================

    // MARK: - Search
    // ======================================
    // Beginning of Search Functions
    // ======================================
    
    // ======================================
    // Beginning of Cogni AI Assistant Functions
    // ======================================
    
    // Published properties for Cogni
    @Published var cogniIsLoading = false
    @Published var cogniConversations: [[String: Any]] = []
    @Published var cogniCurrentConversationHistory: [[String: Any]] = []
    
    func cogniAsk(message: String, conversationId: String? = nil, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        cogniIsLoading = true
        
        let parameters: [String: Any] = [
            "message": message,
            "conversation_id": conversationId ?? ""
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                completion(false, nil, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/cogni/ask")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func cogniGetConversations(completion: @escaping (Bool, [[String: Any]]?, String?) -> Void) {
        cogniIsLoading = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/cogni/conversations")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let conversations = json["conversations"] as? [[String: Any]] {
                        self.cogniConversations = conversations
                        completion(true, conversations, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func cogniGetConversationHistory(conversationId: String, completion: @escaping (Bool, [[String: Any]]?, String?) -> Void) {
        cogniIsLoading = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/cogni/conversations/\(conversationId)")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let messages = json["messages"] as? [[String: Any]] {
                        self.cogniCurrentConversationHistory = messages
                        completion(true, messages, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func cogniClearConversation(conversationId: String, completion: @escaping (Bool, String?) -> Void) {
        cogniIsLoading = true
        
        let parameters: [String: Any] = [
            "conversation_id": conversationId
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                completion(false, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/cogni/conversations/clear")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                completion(true, "Conversation cleared successfully")
            }
        }.resume()
    }
    
    func cogniExplain(content: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        cogniIsLoading = true
        
        let parameters: [String: Any] = [
            "content": content
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                completion(false, nil, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/cogni/explain")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func cogniGenerateQuiz(content: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        cogniIsLoading = true
        
        let parameters: [String: Any] = [
            "content": content
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                completion(false, nil, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/cogni/generate-quiz")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func cogniGenerateReadlist(topic: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        cogniIsLoading = true
        
        let parameters: [String: Any] = [
            "topic": topic
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                completion(false, nil, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/cogni/generate-readlist")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    // Enhanced Cogni Functions
    
    // Model for matched tutors response
    struct MatchedTutorsResponse: Codable {
        let success: Bool
        let tutors: [SearchResultItem]
    }
    
    // Model for educator recommendations response
    struct RecommendationResponse: Codable {
        let success: Bool
        let recommendations: [RecommendedEducator]
        let code: Int
    }
    
    // Make RecommendedEducator public so it can be used outside this file
    public struct RecommendedEducator: Identifiable, Codable {
        let id: String
        let username: String
        let firstName: String
        let lastName: String
        let avatar: String?
        let bio: String?
        let relevanceScore: Int
        let recommendationReason: String?
        let topCourses: [String]
        let topics: [String]
        
        enum CodingKeys: String, CodingKey {
            case id, username, bio, topics
            case firstName = "first_name"
            case lastName = "last_name"
            case avatar
            case relevanceScore = "relevance_score"
            case recommendationReason = "recommendation_reason"
            case topCourses = "top_courses"
        }
    }
    
    // Fetch matched tutors based on user's preferences and learning goals
    func fetchMatchedTutors(completion: @escaping (Bool, [SearchResultItem]?, String?) -> Void) {
        var urlComponents = URLComponents(string: "\(baseURL)/tutors/matched")!
        
        guard let url = urlComponents.url else {
            completion(false, nil, "Invalid URL")
            return
        }
        
        var request = URLRequest(url: url)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                print("Error fetching matched tutors: \(error.localizedDescription)")
                completion(false, nil, error.localizedDescription)
                return
            }
            
            guard let data = data else {
                completion(false, nil, "No data received")
                return
            }
            
            do {
                let decoder = JSONDecoder()
                let response: MatchedTutorsResponse = try decoder.decode(MatchedTutorsResponse.self, from: data)
                if response.success {
                    completion(true, response.tutors, nil)
                } else {
                    completion(false, nil, "Failed to fetch matched tutors")
                }
            } catch {
                print("Matched tutors decoding error: \(error)")
                completion(false, nil, "Failed to decode matched tutors response")
            }
        }.resume()
    }
    
    func enhancedCogniRecommendEducators(completion: @escaping (Bool, [RecommendedEducator]?, String?) -> Void) {
        cogniIsLoading = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/cogni/enhanced/recommend-educators")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.cogniIsLoading = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    let decoder = JSONDecoder()
                    let response: RecommendationResponse = try decoder.decode(RecommendationResponse.self, from: data)
                    if response.success {
                        completion(true, response.recommendations, nil)
                    } else {
                        completion(false, nil, "Failed to get recommendations")
                    }
                } catch {
                    if let responseString = String(data: data, encoding: .utf8) {
                        print("Raw recommendation response:", responseString)
                    }
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Cogni AI Assistant Functions
    // ======================================
    
    // MARK: - Search Functions
    
    func search(query: String, completion: @escaping (Bool, [SearchResultItem]?) -> Void) {
        isLoadingPosts = true
        error = nil
        
        guard !query.isEmpty else {
            error = "Search query cannot be empty"
            isLoadingPosts = false
            completion(false, nil)
            return
        }
        
        var urlComponents = URLComponents(string: "\(baseURL)/search")!
        urlComponents.queryItems = [
            URLQueryItem(name: "query", value: query)
        ]
        
        guard let url = urlComponents.url else {
            error = "Invalid search URL"
            isLoadingPosts = false
            return
        }
        
        var request = URLRequest(url: url)
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.httpMethod = "GET"
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else { return }
            
            DispatchQueue.main.async {
                self.isLoadingPosts = false
                
                if let error = error {
                    self.error = error.localizedDescription
                    return
                }
                
                guard let data = data else {
                    self.error = "No data received"
                    return
                }
                
                do {
                    let response = try JSONDecoder().decode(SearchResponse.self, from: data)
                    if response.success {
                        // Convert SearchResultItems to Posts, filtering out any that aren't posts
                        let posts = response.results.compactMap { searchResult -> Post? in
                            // Skip user results
                            guard !searchResult.isUser else { return nil }
                            
                            return Post(
                                id: Int(searchResult.id!) ?? 0,
                                createdAt: searchResult.createdAt ?? "",
                                updatedAt: searchResult.updatedAt ?? "",
                                title: searchResult.title,
                                body: searchResult.body ?? "",
                                mediaLink: searchResult.mediaLink,
                                visibility: searchResult.visibility ?? "",
                                mediaType: searchResult.mediaType ?? "",
                                mediaThumbnail: searchResult.mediaThumbnail,
                                userId: searchResult.userId ?? "",
                                user: searchResult.user ?? User(firstName: "", lastName: "", username: "", avatar: "", role: ""),
                                isLiked: false,
                                shareUrl: "",
                                // Add the new required parameters with default values
                                isFromFollowedUser: false,
                                isFromRelatedTopic: false,
                                mutualComments: [],
                                comments: []
                            )
                        }
                        self.posts = posts
                        // Call completion handler with search results
                        if let searchResponse = try? JSONDecoder().decode(SearchResponse.self, from: data) {
                            completion(true, searchResponse.results)
                        } else {
                            completion(true, []) // No results found but search was successful
                        }
                    } else {
                        self.posts = []
                        self.error = "Search failed"
                        completion(false, nil)
                    }
                } catch {
                    self.error = "Failed to decode search response"
                    print("Search decoding error:", error)
                    if let responseString = String(data: data, encoding: .utf8) {
                        print("Raw search response:", responseString)
                    }
                    completion(false, nil)
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Search Functions
    // ======================================
    
    // ======================================
    // Beginning of Payment and Subscription Functions
    // ======================================
    
    // Published properties for Payment
    @Published var isLoadingPayment = false
    @Published var paymentMethods: [[String: Any]] = []
    @Published var subscriptions: [[String: Any]] = []
    
    func initializePayment(amount: Double, description: String, email: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        let parameters: [String: Any] = [
            "amount": amount,
            "description": description,
            "email": email
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                completion(false, nil, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/payment/initiate")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func verifyPaymentReference(reference: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        var urlComponents = URLComponents(string: "\(baseURL)/payment/verify")!
        urlComponents.queryItems = [
            URLQueryItem(name: "reference", value: reference)
        ]
        
        guard let url = urlComponents.url else {
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                completion(false, nil, "Invalid URL")
            }
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    // Payment Methods Functions
    func getPaymentMethods(completion: @escaping (Bool, [[String: Any]]?, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/payment-methods")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let methods = json["payment_methods"] as? [[String: Any]] {
                        self.paymentMethods = methods
                        completion(true, methods, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func initiatePaymentMethod(email: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        let parameters: [String: Any] = [
            "email": email
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                completion(false, nil, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/payment-methods/initiate")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func setDefaultPaymentMethod(methodId: String, completion: @escaping (Bool, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/payment-methods/\(methodId)/default")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Refresh payment methods
                self.getPaymentMethods { _, _, _ in }
                
                completion(true, "Payment method set as default")
            }
        }.resume()
    }
    
    func deletePaymentMethod(methodId: String, completion: @escaping (Bool, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/payment-methods/\(methodId)")!)
        request.httpMethod = "DELETE"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                // Refresh payment methods
                self.getPaymentMethods { _, _, _ in }
                
                completion(true, "Payment method deleted")
            }
        }.resume()
    }
    
    // Subscription Functions
    func initiateSubscription(planId: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        let parameters: [String: Any] = [
            "plan_id": planId
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                completion(false, nil, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/subscriptions/initiate")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func createFreeSubscription(planId: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        let parameters: [String: Any] = [
            "plan_id": planId
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                completion(false, nil, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/subscriptions/free")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func getSubscriptions(completion: @escaping (Bool, [[String: Any]]?, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/subscriptions")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let subscriptions = json["subscriptions"] as? [[String: Any]] {
                        self.subscriptions = subscriptions
                        completion(true, subscriptions, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func getCurrentSubscription(completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/subscriptions/current")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func cancelSubscription(completion: @escaping (Bool, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/subscriptions/cancel")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                completion(true, "Subscription canceled")
            }
        }.resume()
    }
    
    // Payment Split
    func getSplitDetails(reference: String, completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/payment-split/details/\(reference)")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    // Educator Earnings
    func getEducatorEarnings(completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/educator/earnings")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func getEducatorBankInfo(completion: @escaping (Bool, [String: Any]?, String?) -> Void) {
        isLoadingPayment = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/educator/earnings/bank-info")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                        completion(true, json, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    func updateEducatorBankInfo(bankCode: String, accountNumber: String, accountName: String, completion: @escaping (Bool, String?) -> Void) {
        isLoadingPayment = true
        
        let parameters: [String: Any] = [
            "bank_code": bankCode,
            "account_number": accountNumber,
            "account_name": accountName
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                completion(false, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/educator/earnings/bank-info")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingPayment = false
                
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                completion(true, "Bank information updated successfully")
            }
        }.resume()
    }
    
    // ======================================
    // End of Payment and Subscription Functions
    // ======================================
    
    // ======================================
    // Beginning of Report Functions
    // ======================================
    
    // Published properties for Reports
    @Published var isLoadingReports = false
    @Published var reports: [[String: Any]] = []
    
    func reportUser(userId: String, reason: String, details: String, completion: @escaping (Bool, String?) -> Void) {
        isLoadingReports = true
        
        let parameters: [String: Any] = [
            "reason": reason,
            "details": details
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingReports = false
                completion(false, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/reports/user/\(userId)")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingReports = false
                
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                completion(true, "User reported successfully")
            }
        }.resume()
    }
    
    func reportPost(postId: Int, reason: String, details: String, completion: @escaping (Bool, String?) -> Void) {
        isLoadingReports = true
        
        let parameters: [String: Any] = [
            "reason": reason,
            "details": details
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingReports = false
                completion(false, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/reports/post/\(postId)")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingReports = false
                
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                completion(true, "Post reported successfully")
            }
        }.resume()
    }
    
    func reportEducator(educatorId: String, reason: String, details: String, completion: @escaping (Bool, String?) -> Void) {
        isLoadingReports = true
        
        let parameters: [String: Any] = [
            "reason": reason,
            "details": details
        ]
        
        guard let postData = try? JSONSerialization.data(withJSONObject: parameters) else {
            DispatchQueue.main.async {
                self.isLoadingReports = false
                completion(false, "Failed to encode request data")
            }
            return
        }
        
        var request = URLRequest(url: URL(string: "\(baseURL)/reports/educator/\(educatorId)")!)
        request.httpMethod = "POST"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = postData
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingReports = false
                
                if let error = error {
                    completion(false, error.localizedDescription)
                    return
                }
                
                completion(true, "Educator reported successfully")
            }
        }.resume()
    }
    
    func getMyReports(completion: @escaping (Bool, [[String: Any]]?, String?) -> Void) {
        isLoadingReports = true
        
        var request = URLRequest(url: URL(string: "\(baseURL)/reports/my")!)
        request.httpMethod = "GET"
        request.addValue("Bearer \(storedBearer)", forHTTPHeaderField: "Authorization")
        
        URLSession.shared.dataTask(with: request) { [weak self] data, response, error in
            guard let self = self, self.handleApiResponse(data, response, error) else {
                DispatchQueue.main.async {
                    completion(false, nil, "Authentication error or network issue")
                }
                return
            }
            
            DispatchQueue.main.async {
                self.isLoadingReports = false
                
                if let error = error {
                    completion(false, nil, error.localizedDescription)
                    return
                }
                
                guard let data = data else {
                    completion(false, nil, "No data received")
                    return
                }
                
                do {
                    if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                       let reports = json["reports"] as? [[String: Any]] {
                        self.reports = reports
                        completion(true, reports, nil)
                    } else {
                        completion(false, nil, "Invalid response format")
                    }
                } catch {
                    completion(false, nil, "Error parsing response: \(error.localizedDescription)")
                }
            }
        }.resume()
    }
    
    // ======================================
    // End of Report Functions
    // ======================================
}

