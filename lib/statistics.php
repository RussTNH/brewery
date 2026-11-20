<?php

// Statistics are stored as a PHP-guarded file so a direct web request cannot
// reveal the totals. Only anonymous aggregate counts are retained.
const BREWERY_STATS_STORE_HEADER = "<?php http_response_code(404); exit; ?>\n";

function brewery_stats_event_keys(): array
{
    return [
        'welcome_view',
        'guide_view',
        'install_click',
        'android_install',
        'ios_instructions'
    ];
}

function brewery_stats_defaults(): array
{
    $stats = [];
    foreach (brewery_stats_event_keys() as $event) {
        $stats[$event] = 0;
    }
    $stats['last_updated'] = null;

    return $stats;
}

function brewery_stats_data_dir(): string
{
    $configured = getenv('BREWERY_STATS_DATA_DIR');
    if ($configured !== false && trim($configured) !== '') {
        return rtrim(trim($configured), "/\\");
    }

    return dirname(__DIR__) . '/live-data/data';
}

function brewery_stats_store_file(): string
{
    return brewery_stats_data_dir() . '/stats.php';
}

function brewery_stats_legacy_file(): string
{
    return dirname(__DIR__) . '/live-data/data/stats.json';
}

function brewery_stats_decode_json(string $json, ?string &$error = null): ?array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        $error = 'Statistics data is not valid JSON.';
        return null;
    }

    $stats = brewery_stats_defaults();
    foreach (brewery_stats_event_keys() as $event) {
        if (!array_key_exists($event, $decoded)) {
            continue;
        }

        $value = $decoded[$event];
        if (is_int($value) && $value >= 0) {
            $stats[$event] = $value;
            continue;
        }

        if (is_string($value) && ctype_digit($value) && strlen($value) < 19) {
            $stats[$event] = (int)$value;
            continue;
        }

        $error = 'Statistics data contains an invalid count.';
        return null;
    }

    if (isset($decoded['last_updated']) && $decoded['last_updated'] !== '') {
        if (!is_string($decoded['last_updated'])) {
            $error = 'Statistics data contains an invalid update time.';
            return null;
        }

        try {
            new DateTimeImmutable($decoded['last_updated']);
        } catch (Exception $exception) {
            $error = 'Statistics data contains an invalid update time.';
            return null;
        }
        $stats['last_updated'] = $decoded['last_updated'];
    }

    return $stats;
}

function brewery_stats_decode_store(string $contents, ?string &$error = null): ?array
{
    if (strncmp($contents, BREWERY_STATS_STORE_HEADER, strlen(BREWERY_STATS_STORE_HEADER)) !== 0) {
        $error = 'Statistics storage is not protected correctly.';
        return null;
    }

    $json = trim(substr($contents, strlen(BREWERY_STATS_STORE_HEADER)));
    if ($json === '') {
        $error = 'Statistics storage is empty.';
        return null;
    }

    return brewery_stats_decode_json($json, $error);
}

function brewery_stats_write_all($handle, string $contents, ?string &$error = null): bool
{
    if (!rewind($handle) || !ftruncate($handle, 0)) {
        $error = 'Unable to prepare statistics storage.';
        return false;
    }

    $length = strlen($contents);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($handle, substr($contents, $offset));
        if ($written === false || $written === 0) {
            $error = 'Unable to write statistics storage.';
            return false;
        }
        $offset += $written;
    }

    if (!fflush($handle)) {
        $error = 'Unable to flush statistics storage.';
        return false;
    }

    return true;
}

function brewery_stats_write_store_locked($handle, array $stats, ?string &$error = null): bool
{
    $json = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $error = 'Unable to encode statistics data.';
        return false;
    }

    return brewery_stats_write_all($handle, BREWERY_STATS_STORE_HEADER . $json . PHP_EOL, $error);
}

function brewery_stats_prepare_store(?string &$error = null): bool
{
    $error = null;
    $dataDir = brewery_stats_data_dir();
    if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
        $error = 'Unable to create the statistics data directory.';
        return false;
    }

    $storeFile = brewery_stats_store_file();
    $storeHandle = fopen($storeFile, 'c+');
    if ($storeHandle === false) {
        $error = 'Unable to open statistics storage.';
        return false;
    }

    if (!flock($storeHandle, LOCK_EX)) {
        fclose($storeHandle);
        $error = 'Unable to lock statistics storage.';
        return false;
    }

    $legacyHandle = null;
    $legacyLocked = false;
    $legacySafeToRemove = false;

    try {
        rewind($storeHandle);
        $contents = stream_get_contents($storeHandle);
        if ($contents === false) {
            $error = 'Unable to read statistics storage.';
            return false;
        }

        $storeIsEmpty = trim($contents) === '';
        $legacyFile = brewery_stats_legacy_file();

        if (is_file($legacyFile)) {
            $legacyHandle = fopen($legacyFile, 'r+');
            if ($legacyHandle === false || !flock($legacyHandle, LOCK_EX)) {
                $error = 'Unable to secure the legacy statistics file.';
                return false;
            }
            $legacyLocked = true;
        }

        if ($storeIsEmpty) {
            $stats = brewery_stats_defaults();

            if ($legacyHandle !== null) {
                rewind($legacyHandle);
                $legacyContents = stream_get_contents($legacyHandle);
                if ($legacyContents === false) {
                    $error = 'Unable to read the legacy statistics file.';
                    return false;
                }

                $legacyContents = trim($legacyContents);
                if ($legacyContents !== '') {
                    $stats = brewery_stats_decode_json($legacyContents, $error);
                    if ($stats === null) {
                        return false;
                    }
                }
            }

            if (!brewery_stats_write_store_locked($storeHandle, $stats, $error)) {
                $writeError = $error;
                $rollbackError = null;
                brewery_stats_write_all($storeHandle, '', $rollbackError);
                $error = $writeError;
                return false;
            }
        } elseif (brewery_stats_decode_store($contents, $error) === null) {
            // Never overwrite a non-empty corrupt store; retain it for recovery.
            return false;
        }

        @chmod($storeFile, 0600);

        if ($legacyHandle !== null) {
            // Linux can unlink the locked file immediately. If the host cannot,
            // erase its public contents before making a second removal attempt.
            if (@unlink($legacyFile)) {
                $legacySafeToRemove = true;
            } else {
                if (!brewery_stats_write_all($legacyHandle, "{}\n", $error)) {
                    return false;
                }
                @chmod($legacyFile, 0600);
                $legacySafeToRemove = true;
            }
        }

        return true;
    } finally {
        if (is_resource($legacyHandle)) {
            if ($legacyLocked) {
                flock($legacyHandle, LOCK_UN);
            }
            fclose($legacyHandle);
            if ($legacySafeToRemove && is_file(brewery_stats_legacy_file())) {
                @unlink(brewery_stats_legacy_file());
            }
        }
        flock($storeHandle, LOCK_UN);
        fclose($storeHandle);
    }
}

function brewery_stats_read(?string &$error = null): array
{
    $error = null;
    if (!brewery_stats_prepare_store($error)) {
        return brewery_stats_defaults();
    }

    $handle = fopen(brewery_stats_store_file(), 'r');
    if ($handle === false) {
        $error = 'Unable to open statistics storage for reading.';
        return brewery_stats_defaults();
    }

    if (!flock($handle, LOCK_SH)) {
        fclose($handle);
        $error = 'Unable to lock statistics storage for reading.';
        return brewery_stats_defaults();
    }

    $contents = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    if ($contents === false) {
        $error = 'Unable to read statistics storage.';
        return brewery_stats_defaults();
    }

    $stats = brewery_stats_decode_store($contents, $error);
    return $stats ?? brewery_stats_defaults();
}

function brewery_stats_increment(string $event, ?string &$error = null): bool
{
    $error = null;
    if (!in_array($event, brewery_stats_event_keys(), true)) {
        $error = 'Unknown statistics event.';
        return false;
    }

    if (!brewery_stats_prepare_store($error)) {
        return false;
    }

    $storeFile = brewery_stats_store_file();
    $handle = fopen($storeFile, 'r+');
    if ($handle === false) {
        $error = 'Unable to open statistics storage.';
        return false;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        $error = 'Unable to lock statistics storage.';
        return false;
    }

    try {
        rewind($handle);
        $contents = stream_get_contents($handle);
        if ($contents === false) {
            $error = 'Unable to read statistics storage.';
            return false;
        }

        $stats = brewery_stats_decode_store($contents, $error);
        if ($stats === null) {
            return false;
        }

        if ($stats[$event] >= PHP_INT_MAX) {
            $error = 'Statistics count has reached the supported limit.';
            return false;
        }

        $stats[$event]++;
        $stats['last_updated'] = gmdate('c');

        if (!brewery_stats_write_store_locked($handle, $stats, $error)) {
            $writeError = $error;
            $rollbackError = null;
            brewery_stats_write_all($handle, $contents, $rollbackError);
            $error = $writeError;
            if ($rollbackError !== null) {
                $error .= ' The previous statistics could not be restored.';
            }
            return false;
        }

        @chmod($storeFile, 0600);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
