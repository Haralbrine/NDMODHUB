<?php
$modsFile = 'mods.json';
$uploadDir = 'uploads/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!file_exists($modsFile)) {
    file_put_contents($modsFile, json_encode(['mods' => []]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['mod_zip'])) {
    $name = trim($_POST['name'] ?? '');
    $author = trim($_POST['author'] ?? 'Anonymous');
    $description = trim($_POST['description'] ?? '');
    $id = strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '', $name));

    if ($name && $id && $_FILES['mod_zip']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['mod_zip']['tmp_name'];
        $fileName = $id . '.zip';
        $destination = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmp, $destination)) {
            // Automatically inspect zip contents
            $contains = [];
            $zip = new ZipArchive();
            if ($zip->open($destination) === TRUE) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    if (str_starts_with($entry, 'levels/') && !in_array('levels/', $contains)) $contains[] = 'levels/';
                    if (str_starts_with($entry, 'audio/') && !in_array('audio/', $contains)) $contains[] = 'audio/';
                    if (str_starts_with($entry, 'video/') && !in_array('video/', $contains)) $contains[] = 'video/';
                }
                $zip->close();
            }

            $data = json_decode(file_get_contents($modsFile), true);
            // Replace old entry if uploading same name
            $data['mods'] = array_values(array_filter($data['mods'], fn($m) => $m['id'] !== $id));
            
            $data['mods'][] = [
                'id' => $id,
                'name' => $name,
                'author' => $author,
                'description' => $description,
                'downloadUrl' => $uploadDir . $fileName,
                'contains' => $contains
            ];

            file_put_contents($modsFile, json_encode($data, JSON_PRETTY_PRINT));
            
            // Redirect back to index.html with the new mod selected
            header("Location: index.html?mod=" . $id);
            exit;
        }
    }
}

// Fallback error redirect
header("Location: index.html?error=upload_failed");
exit;
