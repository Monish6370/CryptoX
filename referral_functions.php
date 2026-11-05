<?php

function createReferralRewards(
    mysqli $conn,
    int $tradingUserId,
    float $tradingFee,
    string $activityReference
): void {
    if ($tradingFee <= 0) {
        return;
    }

    // Get the first-level referrer.
    $stmt = mysqli_prepare(
        $conn,
        "SELECT referred_by FROM users WHERE id = ? LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException("Could not prepare user lookup.");
    }

    mysqli_stmt_bind_param($stmt, "i", $tradingUserId);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Could not find the user's referrer.");
    }

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $referrerId = (int) ($user['referred_by'] ?? 0);
    $visited = [$tradingUserId => true];

    // Process referral levels 1 through 5.
    for ($level = 1; $level <= 5; $level++) {
        if ($referrerId <= 0 || isset($visited[$referrerId])) {
            break;
        }

        $visited[$referrerId] = true;

        // Read the active reward percentage.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT reward_rate
             FROM referral_levels
             WHERE level_number = ? AND is_active = 1
             LIMIT 1"
        );

        if (!$stmt) {
            throw new RuntimeException("Could not prepare level lookup.");
        }

        mysqli_stmt_bind_param($stmt, "i", $level);

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException("Could not read referral level.");
        }

        $result = mysqli_stmt_get_result($stmt);
        $levelData = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$levelData) {
            break;
        }

        $rate = (float) $levelData['reward_rate'];

        if ($rate < 0 || $rate > 100) {
            throw new RuntimeException("Invalid referral reward rate.");
        }

        $rewardAmount = round($tradingFee * $rate / 100, 8);

        if ($rewardAmount > 0) {
            // Avoid inserting the same reward twice.
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id
                 FROM referral_rewards
                 WHERE earner_user_id = ?
                   AND level_number = ?
                   AND activity_reference = ?
                 LIMIT 1"
            );

            if (!$stmt) {
                throw new RuntimeException("Could not check existing reward.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "iis",
                $referrerId,
                $level,
                $activityReference
            );

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException("Could not check referral reward.");
            }

            $result = mysqli_stmt_get_result($stmt);
            $existingReward = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$existingReward) {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO referral_rewards
                    (
                        earner_user_id,
                        referred_user_id,
                        level_number,
                        qualifying_fee,
                        reward_rate,
                        reward_amount,
                        activity_reference,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
                );

                if (!$stmt) {
                    throw new RuntimeException("Could not prepare reward insert.");
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "iiiddds",
                    $referrerId,
                    $tradingUserId,
                    $level,
                    $tradingFee,
                    $rate,
                    $rewardAmount,
                    $activityReference
                );

                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    throw new RuntimeException("Could not save referral reward.");
                }

                mysqli_stmt_close($stmt);
            }
        }

        // Find the next referrer in the chain.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT referred_by FROM users WHERE id = ? LIMIT 1"
        );

        if (!$stmt) {
            throw new RuntimeException("Could not prepare next-referrer lookup.");
        }

        mysqli_stmt_bind_param($stmt, "i", $referrerId);

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new RuntimeException("Could not read next referrer.");
        }

        $result = mysqli_stmt_get_result($stmt);
        $nextUser = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        $referrerId = (int) ($nextUser['referred_by'] ?? 0);
    }
}
