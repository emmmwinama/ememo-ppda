<?php
// memo_utils.php (or a new file MemoNotifier.php)

require_once 'FCMService.php';

class MemoNotifier
{
    private $fcm;
    private $conn;

    public function __construct(mysqli $conn, string $serviceAccountPath)
    {
        $this->conn = $conn;
        $this->fcm  = new FCMService($serviceAccountPath);
    }

    /**
     * Send a push notification for a memo event.
     *
     * @param int    $memoDbId    Internal memo ID.
     * @param string $memoRef     Public memo number.
     * @param string $eventType   e.g. 'Submitted','Endorsed','Updated'
     * @param int    $actorId     User ID who performed the action.
     * @param string $actorName   Name of the actor.
     * @param array  $recipientIds  Array of user IDs to notify.
     * @param array  $extraData   Any extra key/value pairs to include.
     */
    public function notify(
        int $memoDbId,
        string $memoRef,
        string $eventType,
        int $actorId,
        string $actorName,
        array $recipientIds,
        array $extraData = []
    ): void {
        // Build title/body
        $title = "Memo {$eventType} by {$actorName}";
        $body  = "{$actorName} just {$eventType} “{$memoRef}”";

        // Data payload
        $data = array_merge([
            'memo_db_id'  => (string)$memoDbId,
            'memo_id'     => $memoRef,
            'event'       => $eventType,
            'actor_id'    => (string)$actorId,
            'actor_name'  => $actorName,
            'timestamp'   => date('c'),
        ], $extraData);

        // Fetch tokens for all recipients
        $tokens = [];
        $stmt = $this->conn->prepare(
            "SELECT fcm_token FROM user_devices WHERE user_id = ?"
        );
        foreach ($recipientIds as $uid) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $tokens[] = $row['fcm_token'];
            }
        }
        $stmt->close();

        if (!empty($tokens)) {
            try {
                $this->fcm->sendNotification($tokens, $title, $body, $data);
            } catch (Exception $e) {
                error_log("FCM notify error [{$eventType}]: " . $e->getMessage());
            }
        }
    }
}
