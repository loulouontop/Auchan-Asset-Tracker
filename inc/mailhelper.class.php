<?php

class PluginAuchanassettrackerMailhelper
{
    public static function notifyAllocationPending(int $users_id, int $equipment_id): void
    {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Equipment awaiting confirmation', 'auchanassettracker');
        $body = sprintf(
            __('You have equipment awaiting confirmation: %s (%s). Please confirm receipt in Auchan Asset Tracker.', 'auchanassettracker'),
            $eq->fields['name'] ?? ('#' . $equipment_id),
            $eq->fields['serial'] ?? ''
        );
        $link = plugin_auchanassettracker_web_dir() . '/front/confirm.php';
        self::send($users_id, $subject, $body, $link);
    }

    public static function notifyAllocationRejected(int $users_id, int $equipment_id): void
    {
        $eq = new PluginAuchanassettrackerEquipment();
        $eq->getFromDB($equipment_id);
        $subject = __('Allocation rejected by user', 'auchanassettracker');
        $body = sprintf(
            __('User reported they did not receive: %s (%s). Please place it back in a container.', 'auchanassettracker'),
            $eq->fields['name'] ?? ('#' . $equipment_id),
            $eq->fields['serial'] ?? ''
        );
        $link = plugin_auchanassettracker_web_dir() . '/front/equipment.form.php?id=' . $equipment_id;
        self::send($users_id, $subject, $body, $link);
    }

    public static function send(int $users_id, string $subject, string $body, string $link = ''): void
    {
        if ($users_id <= 0) {
            return;
        }

        // Always keep an in-app notice for the recipient (allocator / end user).
        PluginAuchanassettrackerNotice::addForUser(
            $users_id,
            $subject . ' — ' . $body,
            $link
        );

        try {
            $user = new User();
            if (!$user->getFromDB($users_id)) {
                return;
            }
            $email = $user->getDefaultEmail();
            if (!$email) {
                PluginAuchanassettrackerPluginlog::info(
                    "No email for user $users_id — in-app notice stored. Subject: $subject"
                );
                return;
            }

            if (class_exists('GLPIMailer', false)) {
                $mmail = new GLPIMailer();
                $mmail->AddAddress($email);
                $mmail->Subject = '[' . __('Auchan Asset Tracker', 'auchanassettracker') . '] ' . $subject;
                $mmail->Body = $body . ($link !== '' ? "\n\n" . $link : '');
                @$mmail->Send();
                return;
            }

            if (class_exists(\Glpi\Mail\SMTP\SmtpTransport::class, false)
                || class_exists('Symfony\Component\Mailer\Mailer', false)) {
                // Prefer GLPI notification helpers when available.
                if (class_exists('NotificationMailing', false)
                    && method_exists('NotificationMailing', 'send')) {
                    // Best-effort; fall through to log if signature differs.
                }
            }

            PluginAuchanassettrackerPluginlog::info(
                "Mail backend unavailable; in-app notice stored for $email — $subject"
            );
        } catch (Throwable $e) {
            PluginAuchanassettrackerPluginlog::exception($e, 'mail');
        }
    }
}
