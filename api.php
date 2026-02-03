<?php

declare(strict_types=1);

require __DIR__ . '/api/bootstrap.php';

$action = $_POST['action'] ?? $_GET['action'] ?? 'health';

switch ($action) {
    case 'health':
        respond(['ok' => true]);
        break;

    case 'list_resumes':
        $stmt = $pdo->query(
            'SELECT id, name, active_template, active_version_id, created_at, updated_at '
            . 'FROM resumes ORDER BY updated_at DESC'
        );
        respond(['resumes' => $stmt->fetchAll()]);
        break;

    case 'create_resume':
        $name = trim($_POST['name'] ?? '未命名履歷');
        $templateId = $_POST['template_id'] ?? 'teacher';
        $html = $_POST['html'] ?? '';
        $themeJson = $_POST['theme_json'] ?? '{}';
        $fontJson = $_POST['font_json'] ?? '{}';
        $avatarData = $_POST['avatar_data'] ?? '';
        $versionLabel = $_POST['version_label'] ?? '初始版本';

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO resumes (name, active_template, created_at, updated_at) '
            . 'VALUES (:name, :template, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => $name,
            ':template' => $templateId,
        ]);
        $resumeId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO resume_versions '
            . '(resume_id, label, template_id, html_content, theme_json, font_json, avatar_data, created_at, updated_at) '
            . 'VALUES (:resume_id, :label, :template_id, :html_content, :theme_json, :font_json, :avatar_data, NOW(), NOW())'
        );
        $stmt->execute([
            ':resume_id' => $resumeId,
            ':label' => $versionLabel,
            ':template_id' => $templateId,
            ':html_content' => $html,
            ':theme_json' => $themeJson,
            ':font_json' => $fontJson,
            ':avatar_data' => $avatarData,
        ]);
        $versionId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'UPDATE resumes SET active_version_id = :version_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':version_id' => $versionId,
            ':id' => $resumeId,
        ]);
        $pdo->commit();

        respond([
            'resume_id' => $resumeId,
            'version_id' => $versionId,
        ]);
        break;

    case 'list_versions':
        $resumeId = require_param('resume_id');
        $stmt = $pdo->prepare(
            'SELECT id, resume_id, label, template_id, created_at, updated_at '
            . 'FROM resume_versions WHERE resume_id = :resume_id ORDER BY updated_at DESC'
        );
        $stmt->execute([':resume_id' => $resumeId]);
        respond(['versions' => $stmt->fetchAll()]);
        break;

    case 'load_version':
        $versionId = require_param('version_id');
        $stmt = $pdo->prepare(
            'SELECT id, resume_id, label, template_id, html_content, theme_json, font_json, avatar_data '
            . 'FROM resume_versions WHERE id = :id'
        );
        $stmt->execute([':id' => $versionId]);
        $version = $stmt->fetch();
        if (!$version) {
            respond(['error' => 'Version not found.'], 404);
        }
        respond(['version' => $version]);
        break;

    case 'save_version':
        $resumeId = require_param('resume_id');
        $versionId = $_POST['version_id'] ?? '';
        $label = $_POST['label'] ?? '更新版本';
        $templateId = $_POST['template_id'] ?? 'teacher';
        $html = $_POST['html'] ?? '';
        $themeJson = $_POST['theme_json'] ?? '{}';
        $fontJson = $_POST['font_json'] ?? '{}';
        $avatarData = $_POST['avatar_data'] ?? '';

        if ($versionId) {
            $stmt = $pdo->prepare(
                'UPDATE resume_versions SET label = :label, template_id = :template_id, html_content = :html_content, '
                . 'theme_json = :theme_json, font_json = :font_json, avatar_data = :avatar_data, updated_at = NOW() '
                . 'WHERE id = :id AND resume_id = :resume_id'
            );
            $stmt->execute([
                ':label' => $label,
                ':template_id' => $templateId,
                ':html_content' => $html,
                ':theme_json' => $themeJson,
                ':font_json' => $fontJson,
                ':avatar_data' => $avatarData,
                ':id' => $versionId,
                ':resume_id' => $resumeId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO resume_versions '
                . '(resume_id, label, template_id, html_content, theme_json, font_json, avatar_data, created_at, updated_at) '
                . 'VALUES (:resume_id, :label, :template_id, :html_content, :theme_json, :font_json, :avatar_data, NOW(), NOW())'
            );
            $stmt->execute([
                ':resume_id' => $resumeId,
                ':label' => $label,
                ':template_id' => $templateId,
                ':html_content' => $html,
                ':theme_json' => $themeJson,
                ':font_json' => $fontJson,
                ':avatar_data' => $avatarData,
            ]);
            $versionId = (int) $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare(
            'UPDATE resumes SET active_template = :template_id, active_version_id = :version_id, updated_at = NOW() '
            . 'WHERE id = :id'
        );
        $stmt->execute([
            ':template_id' => $templateId,
            ':version_id' => $versionId,
            ':id' => $resumeId,
        ]);

        respond(['version_id' => $versionId]);
        break;

    case 'delete_version':
        $versionId = require_param('version_id');
        $stmt = $pdo->prepare('SELECT resume_id FROM resume_versions WHERE id = :id');
        $stmt->execute([':id' => $versionId]);
        $resumeId = $stmt->fetchColumn();
        if (!$resumeId) {
            respond(['error' => 'Version not found.'], 404);
        }
        $stmt = $pdo->prepare('DELETE FROM resume_versions WHERE id = :id');
        $stmt->execute([':id' => $versionId]);

        $stmt = $pdo->prepare(
            'SELECT id FROM resume_versions WHERE resume_id = :resume_id ORDER BY updated_at DESC LIMIT 1'
        );
        $stmt->execute([':resume_id' => $resumeId]);
        $nextVersion = $stmt->fetchColumn();
        $stmt = $pdo->prepare(
            'UPDATE resumes SET active_version_id = :version_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':version_id' => $nextVersion ?: null,
            ':id' => $resumeId,
        ]);
        respond(['ok' => true, 'active_version_id' => $nextVersion]);
        break;

    case 'delete_resume':
        $resumeId = require_param('resume_id');
        $stmt = $pdo->prepare('SELECT id FROM resumes WHERE id = :id');
        $stmt->execute([':id' => $resumeId]);
        if (!$stmt->fetchColumn()) {
            respond(['error' => 'Resume not found.'], 404);
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM resume_versions WHERE resume_id = :resume_id');
        $stmt->execute([':resume_id' => $resumeId]);
        $stmt = $pdo->prepare('DELETE FROM style_presets WHERE resume_id = :resume_id');
        $stmt->execute([':resume_id' => $resumeId]);
        $stmt = $pdo->prepare('DELETE FROM resumes WHERE id = :id');
        $stmt->execute([':id' => $resumeId]);
        $pdo->commit();
        respond(['ok' => true]);
        break;

    case 'list_style_presets':
        $resumeId = require_param('resume_id');
        $stmt = $pdo->prepare(
            'SELECT id, resume_id, name, theme_json, font_json, created_at '
            . 'FROM style_presets WHERE resume_id = :resume_id ORDER BY created_at DESC'
        );
        $stmt->execute([':resume_id' => $resumeId]);
        respond(['presets' => $stmt->fetchAll()]);
        break;

    case 'save_style_preset':
        $resumeId = require_param('resume_id');
        $name = trim($_POST['name'] ?? '未命名樣式');
        $themeJson = $_POST['theme_json'] ?? '{}';
        $fontJson = $_POST['font_json'] ?? '{}';
        $stmt = $pdo->prepare(
            'INSERT INTO style_presets (resume_id, name, theme_json, font_json, created_at) '
            . 'VALUES (:resume_id, :name, :theme_json, :font_json, NOW())'
        );
        $stmt->execute([
            ':resume_id' => $resumeId,
            ':name' => $name,
            ':theme_json' => $themeJson,
            ':font_json' => $fontJson,
        ]);
        respond(['preset_id' => (int) $pdo->lastInsertId()]);
        break;

    default:
        respond(['error' => 'Unknown action.'], 400);
}
