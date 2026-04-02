<?php
/**
 * SpocSpace - Notification helper
 */
class Notification
{
    /**
     * Create a notification for a user
     * @param string $userId  Target user ID
     * @param string $type    Notification type (vacances_valide, vacances_refuse, changement_accepte, etc.)
     * @param string $title   Short title
     * @param string $message Optional longer message
     * @param string $link    Optional SPA link (e.g. "vacances" or "changements")
     */
    public static function create(string $userId, string $type, string $title, string $message = '', string $link = ''): void
    {
        Db::exec(
            "INSERT INTO notifications (id, user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?, ?)",
            [Uuid::v4(), $userId, $type, $title, $message, $link]
        );
    }

    /**
     * Create notifications for multiple users
     */
    public static function createBulk(array $userIds, string $type, string $title, string $message = '', string $link = ''): void
    {
        if (empty($userIds)) return;
        $pdo = Db::connect();
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (id, user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($userIds as $uid) {
            $stmt->execute([Uuid::v4(), $uid, $type, $title, $message, $link]);
        }
    }

    /**
     * Get unread count for a user
     */
    public static function unreadCount(string $userId): int
    {
        return (int) Db::getOne(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    /**
     * Get notifications for a user (paginated)
     */
    public static function getForUser(string $userId, int $limit = 30, int $offset = 0): array
    {
        return Db::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Mark a single notification as read
     */
    public static function markRead(string $notifId, string $userId): void
    {
        Db::exec(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
            [$notifId, $userId]
        );
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllRead(string $userId): void
    {
        Db::exec(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }
}
