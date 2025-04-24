import Foundation
import SwiftUI

// MARK: - DeepLink Model
enum DeepLink: Equatable {
    case message(conversationId: String)
    case channelMessage(channelId: String)
    case post(postId: String)
    case comment(postId: String, commentId: String)
    case profile(userId: String)
    case course(courseId: String)
    case liveClass(classId: String)
    case none
    
    static func fromNotification(_ notification: [String: Any]) -> DeepLink {
        guard let type = notification["type"] as? String else {
            return .none
        }
        
        switch type {
        case "message":
            if let conversationId = notification["conversation_id"] as? String {
                return .message(conversationId: conversationId)
            }
            
        case "channel_message":
            if let channelId = notification["channel_id"] as? String {
                return .channelMessage(channelId: channelId)
            }
            
        case "comment":
            if let postId = notification["post_id"] as? String,
               let commentId = notification["comment_id"] as? String {
                return .comment(postId: postId, commentId: commentId)
            }
            
        case "like":
            if let postId = notification["post_id"] as? String {
                return .post(postId: postId)
            }
            
        case "follow":
            if let userId = notification["user_id"] as? String {
                return .profile(userId: userId)
            }
            
        case "course":
            if let courseId = notification["course_id"] as? String {
                return .course(courseId: courseId)
            }
            
        case "live_class":
            if let classId = notification["class_id"] as? String {
                return .liveClass(classId: classId)
            }
            
        default:
            break
        }
        
        return .none
    }
    
    static func fromURL(_ url: URL) -> DeepLink {
        guard let components = URLComponents(url: url, resolvingAgainstBaseURL: true),
              let host = components.host else {
            return .none
        }
        
        let pathComponents = components.path.components(separatedBy: "/").filter { !$0.isEmpty }
        
        switch host {
        case "message":
            if pathComponents.count > 0 {
                return .message(conversationId: pathComponents[0])
            }
            
        case "channel":
            if pathComponents.count > 0 {
                return .channelMessage(channelId: pathComponents[0])
            }
            
        case "post":
            if pathComponents.count > 0 {
                if pathComponents.count > 1 && pathComponents[1] == "comment" && pathComponents.count > 2 {
                    return .comment(postId: pathComponents[0], commentId: pathComponents[2])
                } else {
                    return .post(postId: pathComponents[0])
                }
            }
            
        case "profile":
            if pathComponents.count > 0 {
                return .profile(userId: pathComponents[0])
            }
            
        case "course":
            if pathComponents.count > 0 {
                return .course(courseId: pathComponents[0])
            }
            
        case "liveclass":
            if pathComponents.count > 0 {
                return .liveClass(classId: pathComponents[0])
            }
            
        default:
            break
        }
        
        return .none
    }
}

// MARK: - NotificationData Model
struct NotificationData: Identifiable, Codable {
    let id: String
    let type: String
    let title: String
    let message: String
    let sender_name: String?
    let sender_avatar: String?
    let created_at: String
    let data: [String: String]?
    
    var deepLink: DeepLink {
        var notificationDict: [String: Any] = ["type": type]
        
        if let data = data {
            for (key, value) in data {
                notificationDict[key] = value
            }
        }
        
        return DeepLink.fromNotification(notificationDict)
    }
}