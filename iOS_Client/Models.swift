import Foundation


// MARK: - Common Protocols
protocol BaseModel: Codable, Identifiable {}

// MARK: - Feed Response
struct FeedResponse: Codable {
    let posts: [Post]
    let courses: [Course]?
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        if container.contains(.posts) {
            posts = try container.decode([Post].self, forKey: .posts)
        } else {
            do {
                let singleContainer = try decoder.singleValueContainer()
                posts = try singleContainer.decode([Post].self)
            } catch {
                print("Failed to decode posts: \(error)")
                posts = []
            }
        }
        
        courses = try? container.decodeIfPresent([Course].self, forKey: .courses)
    }
    
    enum CodingKeys: String, CodingKey {
        case posts
        case courses
    }
}


struct SearchResultItem: Identifiable, Codable {
    let id: String?
    let createdAt: String?
    let updatedAt: String?
    let title: String?
    let body: String?
    let mediaLink: String?
    let visibility: String?
    let mediaType: String?
    let mediaThumbnail: String?
    let userId: String?
    let user: User?
    let firstName: String?
    let lastName: String?
    let username: String?
    let role: String?
    let avatar: String?
    let email: String?
    let isAdmin: Int?
    let setupCompleted: Bool?
    
    
    var isUser: Bool {
        firstName != nil && lastName != nil && role != nil
    }
    
    enum CodingKeys: String, CodingKey {
        case id
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case title, body
        case mediaLink = "media_link"
        case visibility
        case mediaType = "media_type"
        case mediaThumbnail = "media_thumbnail"
        case userId = "user_id"
        case user
        case firstName = "first_name"
        case lastName = "last_name"
        case username, role, avatar, email
        case isAdmin
        case setupCompleted = "setup_completed"
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)

        if let intId = try container.decodeIfPresent(Int.self, forKey: .id) {
            self.id = String(intId)
        } else {
            self.id = try container.decodeIfPresent(String.self, forKey: .id)
        }
        
        createdAt = try? container.decode(String.self, forKey: .createdAt)
        updatedAt = try? container.decode(String.self, forKey: .updatedAt)
        title = try? container.decode(String?.self, forKey: .title)
        body = try? container.decode(String?.self, forKey: .body)
        mediaLink = try? container.decode(String?.self, forKey: .mediaLink)
        visibility = try? container.decode(String?.self, forKey: .visibility)
        mediaType = try? container.decode(String?.self, forKey: .mediaType)
        mediaThumbnail = try? container.decode(String?.self, forKey: .mediaThumbnail)
        userId = try? container.decode(String?.self, forKey: .userId)
        user = try? container.decode(User?.self, forKey: .user)
        firstName = try? container.decode(String?.self, forKey: .firstName)
        lastName = try? container.decode(String?.self, forKey: .lastName)
        username = try? container.decode(String?.self, forKey: .username)
        role = try? container.decode(String?.self, forKey: .role)
        avatar = try? container.decode(String?.self, forKey: .avatar)
        email = try? container.decode(String?.self, forKey: .email)
        isAdmin = try? container.decode(Int?.self, forKey: .isAdmin)
        setupCompleted = try? container.decode(Bool?.self, forKey: .setupCompleted)
    }
    
    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        try container.encode(id, forKey: .id)
        try container.encode(createdAt, forKey: .createdAt)
        try container.encode(updatedAt, forKey: .updatedAt)
        try container.encode(title, forKey: .title)
        try container.encode(body, forKey: .body)
        try container.encode(mediaLink, forKey: .mediaLink)
        try container.encode(visibility, forKey: .visibility)
        try container.encode(mediaType, forKey: .mediaType)
        try container.encode(mediaThumbnail, forKey: .mediaThumbnail)
        try container.encode(userId, forKey: .userId)
        try container.encode(user, forKey: .user)
        try container.encode(firstName, forKey: .firstName)
        try container.encode(lastName, forKey: .lastName)
        try container.encode(username, forKey: .username)
        try container.encode(role, forKey: .role)
        try container.encode(avatar, forKey: .avatar)
        try container.encode(email, forKey: .email)
        try container.encode(isAdmin, forKey: .isAdmin)
        try container.encode(setupCompleted, forKey: .setupCompleted)
    }
    
    // Custom initializer for creating from RecommendedEducator
    init(from educator: RecommendedEducator) {
        self.id = educator.id
        self.createdAt = nil
        self.updatedAt = nil
        self.title = nil
        self.body = nil
        self.mediaLink = nil
        self.visibility = nil
        self.mediaType = nil
        self.mediaThumbnail = nil
        self.userId = nil
        self.user = nil
        self.firstName = educator.firstName
        self.lastName = educator.lastName
        self.username = educator.username
        self.role = "educator"
        self.avatar = educator.avatar
        self.email = nil
        self.isAdmin = nil
        self.setupCompleted = nil
    }
    func toPost() -> Post? {
        guard !isUser else { return nil }
        return Post(
            id: Int(id!) ?? 0,
            createdAt: createdAt ?? "",
            updatedAt: updatedAt ?? "",
            title: title,
            body: body ?? "",
            mediaLink: mediaLink,
            visibility: visibility ?? "",
            mediaType: mediaType ?? "",
            mediaThumbnail: mediaThumbnail,
            userId: userId ?? "",
            user: user ?? User(firstName: "", lastName: "", username: "", avatar: "", role: ""),
            isLiked: false,
            shareUrl: "",
            isFromFollowedUser: false,
            isFromRelatedTopic: false,
            mutualComments: [],
            comments: []
        )
    }
}

struct SearchResponse: Codable {
    let success: Bool
    let results: [SearchResultItem]
}

// MARK: - Readlist Models
struct ReadlistResponse: Codable {
    let readlist: ReadlistDetail
    let message: String?
}

struct ReadlistDetail: Identifiable, Codable {
    let id: Int
    let title: String
    let description: String?
    let imageUrl: String?
    let isPublic: Bool
    let createdAt: String
    let updatedAt: String
    let user: User?
    let itemsCount: Int?
    let items: [ReadlistItem]
    let shareKey: String?
    let shareUrl: String?
    
    var userId: String? {
        if let username = user?.username {
            return username
        }
        return nil
    }
    
    enum CodingKeys: String, CodingKey {
        case id
        case title
        case description
        case imageUrl = "image_url"
        case isPublic = "is_public"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case user
        case itemsCount = "items_count"
        case items
        case shareKey = "share_key"
        case shareUrl = "share_url"
    }
}

struct ReadlistItem: Identifiable, Codable {
    let id: Int
    let type: String
    let order: Int
    let notes: String?
    let item: ReadlistItemContent
}

struct ReadlistItemContent: Identifiable, Codable {
    let id: Int
    let title: String?
    let body: String?
    let mediaLink: String?
    let mediaType: String
    let userId: String
    
    enum CodingKeys: String, CodingKey {
        case id
        case title
        case body
        case mediaLink = "media_link"
        case mediaType = "media_type"
        case userId = "user_id"
    }
}

// MARK: - Profile Post Model
struct ProfilePost: Codable, Identifiable {
    let id: Int
    let createdAt: String
    let updatedAt: String
    let title: String?
    let body: String?
    let mediaLink: String?
    let visibility: String
    let mediaType: String
    let mediaThumbnail: String?
    let userId: String
    
    enum CodingKeys: String, CodingKey {
        case id
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case title
        case body
        case mediaLink = "media_link"
        case visibility
        case mediaType = "media_type"
        case mediaThumbnail = "media_thumbnail"
        case userId = "user_id"
    }
}

// MARK: - Live Class Models
struct LiveClassesResponse: Decodable {
    let message: String
    let liveClasses: LiveClassesPagination
    
    enum CodingKeys: String, CodingKey {
        case message
        case liveClasses = "live_classes"
    }
}

struct LiveClassesPagination: Decodable {
    let currentPage: Int
    let data: [LiveClass]
    let firstPageUrl: String
    let from: Int
    let lastPage: Int
    let lastPageUrl: String
    let links: [PageLink]
    let nextPageUrl: String?
    let path: String
    let perPage: Int
    let prevPageUrl: String?
    let to: Int
    let total: Int
    
    enum CodingKeys: String, CodingKey {
        case currentPage = "current_page"
        case data
        case firstPageUrl = "first_page_url"
        case from
        case lastPage = "last_page"
        case lastPageUrl = "last_page_url"
        case links
        case nextPageUrl = "next_page_url"
        case path
        case perPage = "per_page"
        case prevPageUrl = "prev_page_url"
        case to
        case total
    }
}

struct PageLink: Decodable {
    let url: String?
    let label: String
    let active: Bool
}

struct LiveClass: Identifiable, Decodable, Equatable {
    let id: Int
    let title: String
    let description: String
    let teacherId: String
    let courseId: Int?
    let lessonId: Int?
    let meetingId: String
    let classType: String
    let settings: ClassSettings
    let scheduledAt: String
    let endedAt: String?
    let status: String
    let createdAt: String
    let updatedAt: String
    let participantCount: Int
    let educator: Educator
    
    var startDate: String { scheduledAt }
    var endDate: String { endedAt ?? "" }
    var thumbnail: String? { nil }
    
    enum CodingKeys: String, CodingKey {
        case id, title, description
        case teacherId = "teacher_id"
        case courseId = "course_id"
        case lessonId = "lesson_id"
        case meetingId = "meeting_id"
        case classType = "class_type"
        case settings
        case scheduledAt = "scheduled_at"
        case endedAt = "ended_at"
        case status
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case participantCount = "participant_count"
        case educator
    }
    
    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        
        id = try container.decode(Int.self, forKey: .id)
        title = try container.decode(String.self, forKey: .title)
        description = try container.decode(String.self, forKey: .description)
        teacherId = try container.decode(String.self, forKey: .teacherId)
        meetingId = try container.decode(String.self, forKey: .meetingId)
        classType = try container.decode(String.self, forKey: .classType)
        settings = try container.decode(ClassSettings.self, forKey: .settings)
        scheduledAt = try container.decode(String.self, forKey: .scheduledAt)
        status = try container.decode(String.self, forKey: .status)
        createdAt = try container.decode(String.self, forKey: .createdAt)
        updatedAt = try container.decode(String.self, forKey: .updatedAt)
        participantCount = try container.decode(Int.self, forKey: .participantCount)
        
        courseId = try container.decodeIfPresent(Int.self, forKey: .courseId)
        lessonId = try container.decodeIfPresent(Int.self, forKey: .lessonId)
        endedAt = try container.decodeIfPresent(String.self, forKey: .endedAt)
        
        if container.contains(.educator) {
            educator = try container.decode(Educator.self, forKey: .educator)
        } else {
            educator = Educator(
                id: teacherId,
                firstName: "Alexandria",
                lastName: "Educator",
                username: "alexandria",
                role: "educator",
                avatar: nil,
                verificationCode: "",
                email: "",
                emailVerifiedAt: nil,
                apiToken: nil,
                createdAt: "",
                updatedAt: "",
                isAdmin: 0,
                googleId: nil,
                name: "Alexandria Educator",
                setupCompleted: true
            )
        }
    }
    
    static func == (lhs: LiveClass, rhs: LiveClass) -> Bool {
        lhs.id == rhs.id &&
        lhs.title == rhs.title &&
        lhs.description == rhs.description &&
        lhs.teacherId == rhs.teacherId &&
        lhs.courseId == rhs.courseId &&
        lhs.lessonId == rhs.lessonId &&
        lhs.meetingId == rhs.meetingId &&
        lhs.classType == rhs.classType &&
        lhs.settings == rhs.settings &&
        lhs.scheduledAt == rhs.scheduledAt &&
        lhs.endedAt == rhs.endedAt &&
        lhs.status == rhs.status &&
        lhs.createdAt == rhs.createdAt &&
        lhs.updatedAt == rhs.updatedAt &&
        lhs.participantCount == rhs.participantCount &&
        lhs.educator.id == rhs.educator.id
    }
}

// MARK: - Class Settings
struct ClassSettings: Decodable, Equatable {
    let enableChat: Bool
    let muteOnJoin: Bool
    let videoOnJoin: Bool
    let enableHandRaising: Bool?
    let allowScreenSharing: Bool?
    
    enum CodingKeys: String, CodingKey {
        case enableChat = "enable_chat"
        case muteOnJoin = "mute_on_join"
        case videoOnJoin = "video_on_join"
        case enableHandRaising = "enable_hand_raising"
        case allowScreenSharing = "allow_screen_sharing"
    }
}

// MARK: - User Models
struct Teacher: Codable, Equatable {
    let id: String
    let firstName: String
    let lastName: String
    let username: String
    let role: String
    let avatar: String?
    let verificationCode: String
    let email: String
    let emailVerifiedAt: String?
    let apiToken: String?
    let createdAt: String
    let updatedAt: String
    let isAdmin: Int
    let setupCompleted: Bool
    
    enum CodingKeys: String, CodingKey {
        case id
        case firstName = "first_name"
        case lastName = "last_name"
        case username
        case role
        case avatar
        case verificationCode = "verification_code"
        case email
        case emailVerifiedAt = "email_verified_at"
        case apiToken = "api_token"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case isAdmin
        case setupCompleted = "setup_completed"
    }
}

struct Educator: Decodable, Equatable {
    let id: String
    let firstName: String
    let lastName: String
    let username: String
    let role: String
    let avatar: String?
    let verificationCode: String
    let email: String
    let emailVerifiedAt: String?
    let apiToken: String?
    let createdAt: String
    let updatedAt: String
    let isAdmin: Int
    let googleId: String?
    let name: String
    let setupCompleted: Bool
    
    enum CodingKeys: String, CodingKey {
        case id, username, role, avatar, email
        case firstName = "first_name"
        case lastName = "last_name"
        case verificationCode = "verification_code"
        case emailVerifiedAt = "email_verified_at"
        case apiToken = "api_token"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case isAdmin
        case googleId = "google_id"
        case name
        case setupCompleted = "setup_completed"
    }
}

// MARK: - Profile Response
struct ProfileResponse: Codable {
    let posts: [ProfilePost]?
    let username: String
    let fullName: String?
    let firstName: String
    let lastName: String
    let avatar: String?
    let bio: String?
    let followers: Int
    let following: Int
    let likes: [String]
    let role: String?
    let educatorProfile: EducatorProfile?
    
    enum CodingKeys: String, CodingKey {
        case posts, username, avatar, bio, followers, following, likes
        case fullName = "full_name"
        case firstName = "first_name"
        case lastName = "last_name"
        case educatorProfile = "educator_profile"
        case role = "role"
    }
}

// MARK: - Channel Models
struct Channel: Codable, Identifiable {
    let id: String
    let title: String
    let description: String?
    let creator_id: String
    let share_link: String
    let is_active: Bool?
    let max_members: Int
    let created_at: String
    let updated_at: String
    let creator: User?
    let messages: [ChannelMessage]?
    let members: [ChannelMember]?
    var unread_count: Int?
    let user_role: String?
    
    var isMember: Bool {
        return user_role != nil
    }
    
    var isAdmin: Bool {
        return user_role == "admin"
    }
    
    var formattedCreatedAt: String {
        return created_at
    }
    
    var memberCount: Int {
        return members?.count ?? 0
    }
    
    var lastActivity: String {
        if let latestMessage = messages?.sorted(by: { $0.created_at > $1.created_at }).first {
            return latestMessage.created_at
        }
        return updated_at
    }
}

struct ChannelMessage: Codable, Identifiable {
    let id: String
    let channel_id: String
    let sender_id: String
    let body: String
    let attachment: String?
    let attachment_type: String?
    let read_by: [String]?
    let created_at: String
    let updated_at: String
    let sender: User?
    
    func isReadBy(userId: String) -> Bool {
        return read_by?.contains(userId) ?? false
    }
    
    var formattedTime: String {
        return created_at
    }
}

struct ChannelMember: Codable, Identifiable {
    let id: String
    let channel_id: String
    let user_id: String
    let role: String
    let is_active: Bool?
    let joined_at: String?
    let last_read_at: String?
    let created_at: String
    let updated_at: String
    let user: User?
    
    var isAdmin: Bool {
        return role == "admin"
    }
}

// MARK: - Channel Response Models
struct ChannelsResponse: Codable {
    let channels: [Channel]
}

struct ChannelDetailResponse: Codable {
    let channel: Channel
}

struct ChannelResponse: Codable {
    let channel: Channel?
    let message: String?
}

struct ChannelMessageResponse: Codable {
    let message: ChannelMessage?
}

struct ChannelMemberResponse: Codable {
    let member: ChannelMember?
    let message: String?
}

struct ShareLinkResponse: Codable {
    let share_link: String
}

// MARK: - Educator Profile Models
struct EducatorProfile: Codable {
    let qualifications: [Qualification]?
    let teachingStyle: String?
    let availability: [Availability]?
    let hireRate: String
    let hireCurrency: String
    let socialLinks: [String]?
    let averageRating: Double
    let ratingsCount: Int
    let recentRatings: [Rating]?
    let description: String
    
    enum CodingKeys: String, CodingKey {
        case qualifications
        case teachingStyle = "teaching_style"
        case availability
        case hireRate = "hire_rate"
        case hireCurrency = "hire_currency"
        case socialLinks = "social_links"
        case averageRating = "average_rating"
        case ratingsCount = "ratings_count"
        case recentRatings = "recent_ratings"
        case description
    }
}

struct Qualification: Codable {
    let title: String
    let institution: String
    let year: String?
}

struct Availability: Codable {
    let day: String
    let startTime: String
    let endTime: String
    
    enum CodingKeys: String, CodingKey {
        case day
        case startTime = "start_time"
        case endTime = "end_time"
    }
}

struct Rating: Codable {
    let id: Int
    let rating: Double
    let comment: String?
    let createdAt: String
    let user: User
    
    enum CodingKeys: String, CodingKey {
        case id, rating, comment
        case createdAt = "created_at"
        case user
    }
}

// MARK: - Post Model
struct Post: BaseModel {
    let id: Int
    let createdAt: String
    let updatedAt: String
    let title: String?
    let body: String
    let mediaLink: String?
    let visibility: String
    let mediaType: String
    let mediaThumbnail: String?
    let userId: String
    let user: User
    let isLiked: Bool?
    let shareUrl: String?
    
    let isFromFollowedUser: Bool?
    let isFromRelatedTopic: Bool?
    let mutualComments: [MutualComment]?
    let comments: [Comment]?
    var originalFilename: String?
    var mimeType: String?
    var fileExtension: String?
    
    enum CodingKeys: String, CodingKey {
        case id
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case title
        case body
        case mediaLink = "media_link"
        case visibility
        case mediaType = "media_type"
        case mediaThumbnail = "media_thumbnail"
        case userId = "user_id"
        case user
        case isLiked = "is_liked"
        case isFromFollowedUser = "is_from_followed_user"
        case isFromRelatedTopic = "is_from_related_topic"
        case mutualComments = "mutual_comments"
        case comments
        case originalFilename = "original_filename"
        case mimeType = "mime_type"
        case fileExtension = "file_extension"
        case shareUrl = "share_url"
    }
}

struct MutualComment: Codable {
    let id: Int
    let content: String
    let createdAt: String
    let user: MutualCommentUser
    
    enum CodingKeys: String, CodingKey {
        case id
        case content
        case createdAt = "created_at"
        case user
    }
}

struct MutualCommentUser: Codable {
    let id: String
    let username: String
    let firstName: String
    let lastName: String
    let avatar: String?
    
    enum CodingKeys: String, CodingKey {
        case id
        case username
        case firstName = "first_name"
        case lastName = "last_name"
        case avatar
    }
}

// MARK: - User Model
struct User: Codable {
    let firstName: String
    let lastName: String
    let username: String
    let avatar: String?
    let role: String?
    
    var fullName: String {
        "\(firstName) \(lastName)"
    }
    
    enum CodingKeys: String, CodingKey {
        case firstName = "first_name"
        case lastName = "last_name"
        case username
        case avatar
        case role
    }
}

struct CommentUser: Codable {
    let id: String
    let firstName: String
    let lastName: String
    let username: String
    let role: String
    let avatar: String?
    let verificationCode: String?
    let email: String
    let emailVerifiedAt: String?
    let apiToken: String?
    let createdAt: String
    let updatedAt: String
    let isAdmin: Int
    let setupCompleted: Bool
    
    var fullName: String {
        "\(firstName) \(lastName)"
    }
    
    enum CodingKeys: String, CodingKey {
        case id
        case firstName = "first_name"
        case lastName = "last_name"
        case username, role, avatar
        case verificationCode = "verification_code"
        case email
        case emailVerifiedAt = "email_verified_at"
        case apiToken = "api_token"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case isAdmin
        case setupCompleted = "setup_completed"
    }
}

// MARK: - Course Category
struct CourseCategory: Codable, Identifiable {
    let id: Int
    let name: String
    let description: String?
    let courses: [Course]?
    
    enum CodingKeys: String, CodingKey {
        case id, name, description, courses
    }
}

// MARK: - Comment Models
struct CommentsResponse: Codable {
    let comments: [Comment]?
    let message: String?
    
    init(from decoder: Decoder) throws {
        do {
            let container = try decoder.container(keyedBy: CodingKeys.self)
            if container.contains(.comments) {
                comments = try container.decode([Comment].self, forKey: .comments)
                message = try? container.decodeIfPresent(String.self, forKey: .message)
            } else {
                do {
                    let singleContainer = try decoder.singleValueContainer()
                    comments = try singleContainer.decode([Comment].self)
                    message = nil
                } catch {
                    let errorContainer = try decoder.container(keyedBy: CodingKeys.self)
                    message = try? errorContainer.decode(String.self, forKey: .message)
                    comments = nil
                }
            }
        } catch {
            do {
                let singleContainer = try decoder.singleValueContainer()
                comments = try singleContainer.decode([Comment].self)
                message = nil
            } catch {
                comments = nil
                message = "Failed to decode response: \(error.localizedDescription)"
            }
        }
    }
    
    enum CodingKeys: String, CodingKey {
        case comments
        case message
    }
}

struct Comment: Codable, Identifiable {
    let id: Int
    let postId: Int
    let userId: String
    let content: String
    let createdAt: String
    let updatedAt: String
    let user: CommentUser
    
    enum CodingKeys: String, CodingKey {
        case id
        case postId = "post_id"
        case userId = "user_id"
        case content
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case user
    }
}
// MARK: - Notification Models
struct NotificationResponse: Codable {
    let notifications: [NotificationData]
}

struct NotificationData: Codable, Identifiable {
    let id: String
    let type: String
    let data: NotificationDataContent
    let seen: Int
    let created_at: String
    let updated_at: String
    var read_at: String?
    let notifiable_id: String
    let notifiable_type: String
    
    // Computed properties for notification types
    var isLike: Bool { type.contains("LikeNotification") }
    var isComment: Bool { type.contains("CommentNotification") }
    var isMessage: Bool { type.contains("NewMessage") }
    var isFollow: Bool { type.contains("followedNotification") }
    var isMention: Bool { type.contains("Mention") }
    var isBroadcast: Bool { type.contains("BroadcastNotification") }
    
    // Get post ID as Int for direct navigation
    var postId: Int? {
        guard let postIdString = data.post_id, let id = Int(postIdString) else {
            return nil
        }
        return id
    }
    
    // Deep link navigation type
    var deepLinkType: NotificationDeepLinkType {
        if isComment || isLike {
            return .post
        } else if isMessage {
            return .conversation
        } else if isFollow {
            return .profile
        } else if isMention {
            return .mention
        } else {
            return .none
        }
    }
}

struct NotificationDataContent: Codable {
    let avatar: String?
    let message: String?
    
    private let _post_id: CodableValue?
    
    var post_id: String? {
        if let stringValue = _post_id?.stringValue {
            return stringValue
        }
        if let intValue = _post_id?.intValue {
            return String(intValue)
        }
        return nil
    }
    
    let liked_by: String?
    let liked_by_username: String?
    let comment_id: Int?
    let commented_by: String?
    let sender_id: String?
    let sender_name: String?
    let sender_avatar: String?
    let message_id: String?
    let conversation_id: String?
    let follower_id: String?
    let preview: String?
    
    enum CodingKeys: String, CodingKey {
        case avatar, message, liked_by, liked_by_username, comment_id, commented_by
        case sender_id, sender_name, sender_avatar, message_id, conversation_id
        case follower_id, preview
        case _post_id = "post_id"
    }
    
    var displayName: String {
        return liked_by_username ?? sender_name ?? ""
    }
    
    var displayAvatar: String? {
        return avatar ?? sender_avatar
    }
}

struct CodableValue: Codable {
    let stringValue: String?
    let intValue: Int?
    
    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        
        if let value = try? container.decode(String.self) {
            stringValue = value
            intValue = nil
            return
        }
        
        if let value = try? container.decode(Int.self) {
            intValue = value
            stringValue = nil
            return
        }
        
        stringValue = nil
        intValue = nil
    }
    
    func encode(to encoder: Encoder) throws {
        var container = encoder.singleValueContainer()
        
        if let stringValue = stringValue {
            try container.encode(stringValue)
        } else if let intValue = intValue {
            try container.encode(intValue)
        } else {
            try container.encodeNil()
        }
    }
}

// Deep link type enum for navigation
enum NotificationDeepLinkType {
    case post
    case conversation
    case profile
    case mention
    case none
}



// MARK: - Topic Model
struct Topic: Codable, Identifiable {
    let id: Int
    let name: String
    let createdAt: String?
    let updatedAt: String?
    
    enum CodingKeys: String, CodingKey {
        case id, name
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }
}

// MARK: - Course Model
struct Course: Codable, Identifiable {
    let id: Int
    let description: String
    let title: String
    let videoUrl: String?
    let price: String
    let likesCount: Int
    let userId: String
    let topicId: Int
    let createdAt: String
    let updatedAt: String
    let fileUrl: String?
    let isEnrolled: Bool
    let enrollment: String?
    let user: User
    let topic: Topic
    
    enum CodingKeys: String, CodingKey {
        case id, description, title, price, user, topic
        case videoUrl = "video_url"
        case likesCount = "likes_count"
        case userId = "user_id"
        case topicId = "topic_id"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case fileUrl = "file_url"
        case isEnrolled = "is_enrolled"
        case enrollment
    }
}

// MARK: - Courses Response Models
struct CoursesByTopic: Codable {
    let topic: Topic
    let courses: [Course]
}

struct CoursesResponse: Codable {
    let coursesByTopic: [CoursesByTopic]
    let recommendedCourses: [Course]
    
    enum CodingKeys: String, CodingKey {
        case coursesByTopic = "courses_by_topic"
        case recommendedCourses = "recommended_courses"
    }
}

// MARK: - Profile Models
struct Profile: Codable {
    let user: ProfileUser
    let posts: [Post]
    let followers: Int
    let following: Int
    let isFollowing: Bool
    
    enum CodingKeys: String, CodingKey {
        case user, posts, followers, following
        case isFollowing = "is_following"
    }
}

struct ProfileUser: Codable {
    let id: String
    let firstName: String
    let lastName: String
    let username: String
    let email: String
    let avatar: String?
    let role: String
    let isAdmin: Int
    let setupCompleted: Bool
    let createdAt: String
    let updatedAt: String
    
    var fullName: String {
        "\(firstName) \(lastName)"
    }
    
    enum CodingKeys: String, CodingKey {
        case id, username, email, avatar, role
        case firstName = "first_name"
        case lastName = "last_name"
        case isAdmin = "is_admin"
        case setupCompleted = "setup_completed"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
    }
}

// MARK: - Educator Models
struct EducatorData: Codable, Identifiable {
    let id: String
    let username: String
    let firstName: String
    let lastName: String
    let avatar: String?
    let createdAt: String
    let coursesCount: Int
    let followersCount: Int
    let topics: [String]
    let fullName: String
    let isFollowed: Bool?
    let isCurrentUser: Bool?
    let setupCompleted: Bool
    
    enum CodingKeys: String, CodingKey {
        case id
        case username
        case firstName = "first_name"
        case lastName = "last_name"
        case avatar
        case createdAt = "created_at"
        case coursesCount = "courses_count"
        case followersCount = "followers_count"
        case topics
        case fullName = "full_name"
        case isFollowed = "is_followed"
        case isCurrentUser = "is_current_user"
        case setupCompleted = "setup_completed"
    }
}

struct EducatorsPaginatedResponse: Codable {
    let currentPage: Int
    let data: [EducatorData]
    let firstPageUrl: String
    let from: Int
    let lastPage: Int
    let lastPageUrl: String
    let links: [PaginationLink]
    let nextPageUrl: String?
    let path: String
    let perPage: Int
    let prevPageUrl: String?
    let to: Int
    let total: Int
    
    enum CodingKeys: String, CodingKey {
        case currentPage = "current_page"
        case data
        case firstPageUrl = "first_page_url"
        case from
        case lastPage = "last_page"
        case lastPageUrl = "last_page_url"
        case links
        case nextPageUrl = "next_page_url"
        case path
        case perPage = "per_page"
        case prevPageUrl = "prev_page_url"
        case to
        case total
    }
}

struct PaginationLink: Codable {
    let url: String?
    let label: String
    let active: Bool
}

struct EducatorsResponse: Codable {
    let educators: EducatorsPaginatedResponse
    let total: Int
    let perPage: Int
    let currentPage: Int
    let lastPage: Int
    
    enum CodingKeys: String, CodingKey {
        case educators
        case total
        case perPage = "per_page"
        case currentPage = "current_page"
        case lastPage = "last_page"
    }
}

// MARK: - Conversation Models
struct Conversation: Identifiable, Codable {
    var id: String
    var conversation_id: String?
    var userOneId: String
    var userTwoId: String
    var lastMessageAt: String
    var createdAt: String
    var updatedAt: String
    var otherUser: User
    var unreadCount: Int?
    var latestMessage: Message?
    var messages: [Message]?
    
    enum CodingKeys: String, CodingKey {
        case id
        case conversation_id = "conversation_id"
        case userOneId = "user_one_id"
        case userTwoId = "user_two_id"
        case lastMessageAt = "last_message_at"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case otherUser = "other_user"
        case unreadCount = "unread_count"
        case latestMessage = "latest_message"
        case messages
    }
}

struct SendMessageResponse: Codable {
    let message: Message
    let conversation: ConversationReference
    
    struct ConversationReference: Codable {
        let id: String
        let conversationId: String
        let otherUser: User
        
        enum CodingKeys: String, CodingKey {
            case id
            case otherUser = "other_user"
            case conversationId = "conversation_id"
        }
    }
}

struct LatestMessage: Codable {
    let id: String
    let conversationId: String
    let senderId: String
    let body: String
    let attachment: String?
    let attachmentType: String?
    let isRead: Bool?
    let createdAt: String
    let updatedAt: String
    let sender: User?
    
    enum CodingKeys: String, CodingKey {
        case id
        case conversationId = "conversation_id"
        case senderId = "sender_id"
        case body
        case attachment
        case attachmentType = "attachment_type"
        case isRead = "is_read"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case sender
    }
}

struct Message: Identifiable, Codable, Equatable {
    var id: String
    var conversationId: String
    var senderId: String
    var body: String
    var createdAt: String
    var updatedAt: String
    var isRead: Bool
    var attachment: String?
    var attachmentType: String?
    var sender: User?
    var deleted_at: String?
    
    enum CodingKeys: String, CodingKey {
        case id
        case conversationId = "conversation_id"
        case senderId = "sender_id"
        case body
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case isRead = "is_read"
        case attachment
        case attachmentType = "attachment_type"
        case sender
        case deleted_at
    }
    
    static func == (lhs: Message, rhs: Message) -> Bool {
        return lhs.id == rhs.id
    }
}

// MARK: - Conversation Extension
extension Conversation {
    init(id: String, conversationId: String, userOneId: String, userTwoId: String, lastMessageAt: String, createdAt: String, updatedAt: String,
         otherUser: User, unreadCount: Int?, latestMessage: Message?, messages: [Message]) {
        self.id = id
        self.conversation_id = conversationId
        self.userOneId = userOneId
        self.userTwoId = userTwoId
        self.lastMessageAt = lastMessageAt
        self.createdAt = createdAt
        self.updatedAt = updatedAt
        self.otherUser = otherUser
        self.unreadCount = unreadCount
        self.latestMessage = latestMessage
        self.messages = messages
    }
}

// MARK: - Response Models
struct APIResponse<T: Codable>: Codable {
    let success: Bool
    let message: String?
    let data: T?
}

struct LikeResponse: Codable {
    let success: Bool
    let message: String
    let isLiked: Bool
    
    enum CodingKeys: String, CodingKey {
        case success, message
        case isLiked = "is_liked"
    }
}

// MARK: - UI Models
struct WelcomeCard: Identifiable {
    let id = UUID()
    let title: String
    let subtitle: String
}
