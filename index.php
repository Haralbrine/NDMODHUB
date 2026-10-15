<?php
$modsFile = 'mods.json';
$uploadDir = 'uploads/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!file_exists($modsFile)) {
    file_put_contents($modsFile, json_encode(['mods' => []]));
}

$message = '';

// Handle Mod Upload via PHP
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
            // Automatically inspect zip contents for levels/, audio/, video/
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
            // Remove old entry if updating same mod name
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
            header("Location: ?mod=" . $id);
            exit;
        } else {
            $message = "Error moving uploaded file.";
        }
    } else {
        $message = "Please provide a valid mod name and zip file.";
    }
}

$currentModId = $_GET['mod'] ?? null;
$modsData = json_decode(file_get_contents($modsFile), true);
$activeMod = null;

if ($currentModId) {
    foreach ($modsData['mods'] as $m) {
        if ($m['id'] === $currentModId) {
            $activeMod = $m;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $activeMod ? htmlspecialchars($activeMod['name']) . ' - ' : ''; ?>Narbacular Drop Mod Hub</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #121212; color: #e0e0e0; margin: 0; padding: 0; }
        header { background: #1f1f1f; padding: 2rem; text-align: center; border-bottom: 2px solid #333; }
        header h1 a { color: #ff7b00; text-decoration: none; }
        main { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .card, .upload-box { background: #1e1e1e; border: 1px solid #333; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; }
        .mod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; }
        .mod-card { background: #1a1a1a; border: 1px solid #333; padding: 1.2rem; border-radius: 6px; display: flex; flex-direction: column; justify-content: space-between; }
        input, textarea, button { width: 100%; padding: 0.7rem; margin: 0.5rem 0 1rem 0; background: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        button, .btn { background: #ff7b00; color: #fff; border: none; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; padding: 0.6rem 1.2rem; border-radius: 4px; }
        button:hover, .btn:hover { background: #e56d00; }
        .tag { background: #2a2a2a; color: #ffb703; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; margin-right: 0.3rem; display: inline-block; margin-top: 0.5rem;}
        .msg { background: #2d3748; color: #63b3ed; padding: 0.8rem; border-radius: 4px; margin-bottom: 1rem; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: #ff7b00; text-decoration: none; }
    </style>
</head>
<body>
    <header>
        <h1><a href="index.php">ND MOD HUB</a></h1>
        <p>Custom levels, audio, and video packages for Narbacular Drop</p>
    </header>

    <main>
        <?php if ($message): ?>
            <div class="msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($activeMod): ?>
            <a href="index.php" class="back-link">&larr; Back to all mods</a>
            <div class="card">
                <h2><?php echo htmlspecialchars($activeMod['name']); ?></h2>
                <p>By <strong><?php echo htmlspecialchars($activeMod['author']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($activeMod['description'])); ?></p>
                
                <h3>Package Contents:</h3>
                <div>
                    <?php foreach ($activeMod['contains'] as $c): ?>
                        <span class="tag">📁 <?php echo htmlspecialchars($c); ?></span>
                    <?php endforeach; ?>
                </div>
                <br><br>
                <a href="<?php echo htmlspecialchars($activeMod['downloadUrl']); ?>" class="btn" download>Download Mod (.zip)</a>
            </div>
        <?php else: ?>
            <div class="upload-box">
                <h3>Upload a New Mod</h3>
                <form action="index.php" method="POST" enctype="multipart/form-data">
                    <label>Mod Name:</label>
                    <input type="text" name="name" required placeholder="e.g. My Custom Levels">
                    
                    <label>Author:</label>
                    <input type="text" name="author" required placeholder="Your name">
                    
                    <label>Description:</label>
                    <textarea name="description" rows="3" placeholder="Briefly describe your levels or assets..."></textarea>
                    
                    <label>Mod Zip File (containing levels/, audio/, or video/):</label>
                    <input type="file" name="mod_zip" accept=".zip" required>
                    
                    <button type="submit">Upload & Publish</button>
                </form>
            </div>

            <h2>Available Mods</h2>
            <div class="mod-grid">
                <?php if (empty($modsData['mods'])): ?>
                    <p>No mods uploaded yet. Be the first!</p>
                <?php else: ?>
                    <?php foreach ($modsData['mods'] as $mod): ?>
                        <div class="mod-card">
                            <div>
                                <h3><?php echo htmlspecialchars($mod['name']); ?></h3>
                                <p style="font-size: 0.85rem; color: #aaa;">By <?php echo htmlspecialchars($mod['author']); ?></p>
                                <p><?php echo htmlspecialchars(substr($mod['description'], 0, 80)); ?>...</p>
                                <div>
                                    <?php foreach ($mod['contains'] as $c): ?>
                                        <span class="tag"><?php echo htmlspecialchars($c); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <br>
                            <a href="?mod=<?php echo urlencode($mod['id']); ?>" class="btn">View Page</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
