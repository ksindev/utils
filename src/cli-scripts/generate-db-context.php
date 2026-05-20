#!/usr/bin/env php
<?php
/**
 * PI Portal — Production Database Context Generator
 * PHP 7.1+ | MySQL 8.0+
 *
 * Generates a Markdown file containing:
 *   - Table inventory (row counts, sizes, engines)
 *   - Per-table column definitions (name, type, nullability, default, comment)
 *   - Index definitions
 *   - FK constraints (if declared in the schema)
 *   - Full DDL via SHOW CREATE TABLE (collapsed)
 *   - Cross-cutting schema pattern summary
 *
 * Usage:
 *   php generate_db_context.php
 *
 * Progress is printed to STDERR; the .md file is written to OUTPUT_FILE.
 * The script never reads or outputs any data rows.
 */
declare(strict_types=1);

// ══════════════════════════════════════════════════════════════════════════════
// CONFIGURATION — fill in before running
// ══════════════════════════════════════════════════════════════════════════════

$DB_HOST = 'localhost';
$DB_PORT = 3306;
$DB_USER = 'your_user';
$DB_PASS = 'your_password';
$DB_NAME = 'your_database';

// Where to write the output
$OUTPUT_FILE = __DIR__ . '/pi_portal_db_context.md';

// true  → runs SELECT COUNT(*) per table (accurate but slow on large DBs)
// false → uses information_schema.TABLES.TABLE_ROWS (fast ±10% estimate)
$EXACT_ROW_COUNTS = true;

// Only process tables whose names start with one of these strings.
// Leave empty to process every table in $DB_NAME.
// Example: ['pi_', 'ai_'] skips any unrelated shared tables.
$TABLE_PREFIX_FILTER = [];

// ══════════════════════════════════════════════════════════════════════════════

// ── Helpers ──────────────────────────────────────────────────────────────────

function err(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
}

function human_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1)    . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 1)        . ' KB';
    return $bytes . ' B';
}

function fmt(int $n): string
{
    return number_format($n);
}

// Escape a value so it does not break a markdown table cell.
function md(string $s): string
{
    return str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], $s);
}

// ── Connect ───────────────────────────────────────────────────────────────────

err("Connecting to {$DB_HOST}/{$DB_NAME} …");

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    err("Connection failed: " . $e->getMessage());
    exit(1);
}

$mysqlVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
$generatedAt  = date('Y-m-d H:i:s');

// ── Fetch table list + IS stats ───────────────────────────────────────────────

err("Fetching table list from information_schema …");

$stmt = $pdo->prepare("
    SELECT
        t.TABLE_NAME,
        t.ENGINE,
        t.TABLE_ROWS,
        t.DATA_LENGTH,
        t.INDEX_LENGTH,
        t.AUTO_INCREMENT,
        t.TABLE_COLLATION,
        t.TABLE_COMMENT,
        COUNT(c.COLUMN_NAME) AS COLUMN_COUNT
    FROM information_schema.TABLES t
    LEFT JOIN information_schema.COLUMNS c
           ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
          AND c.TABLE_NAME   = t.TABLE_NAME
    WHERE t.TABLE_SCHEMA = ?
      AND t.TABLE_TYPE   = 'BASE TABLE'
    GROUP BY
        t.TABLE_NAME, t.ENGINE, t.TABLE_ROWS, t.DATA_LENGTH,
        t.INDEX_LENGTH, t.AUTO_INCREMENT, t.TABLE_COLLATION, t.TABLE_COMMENT
    ORDER BY t.TABLE_NAME
");
$stmt->execute([$DB_NAME]);
$allTables = $stmt->fetchAll();

// Apply prefix filter
if (!empty($TABLE_PREFIX_FILTER)) {
    $allTables = array_values(array_filter(
        $allTables,
        function (array $t) use ($TABLE_PREFIX_FILTER): bool {
            foreach ($TABLE_PREFIX_FILTER as $prefix) {
                if (strpos($t['TABLE_NAME'], $prefix) === 0) return true;
            }
            return false;
        }
    ));
}

$totalTables = count($allTables);
err("Found {$totalTables} tables.");

// ── Exact row counts (optional) ───────────────────────────────────────────────

$exactCounts = [];

if ($EXACT_ROW_COUNTS) {
    err("Counting rows … (this can take several minutes on large databases)");
    foreach ($allTables as $i => $t) {
        $tbl = $t['TABLE_NAME'];
        $num = $i + 1;
        err("  [{$num}/{$totalTables}] COUNT(*) on `{$tbl}`");
        $exactCounts[$tbl] = (int) $pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
    }
}

// ── Open output file ──────────────────────────────────────────────────────────

$fh = fopen($OUTPUT_FILE, 'w');
if (!$fh) {
    err("Cannot open output file: {$OUTPUT_FILE}");
    exit(1);
}

function w(string $line = ''): void
{
    global $fh;
    fwrite($fh, $line . "\n");
}

// ── Summary header ────────────────────────────────────────────────────────────

$totalData  = (int) array_sum(array_column($allTables, 'DATA_LENGTH'));
$totalIndex = (int) array_sum(array_column($allTables, 'INDEX_LENGTH'));
$totalEst   = (int) array_sum(array_column($allTables, 'TABLE_ROWS'));
$totalExact = $EXACT_ROW_COUNTS ? (int) array_sum($exactCounts) : null;

w("# PI Portal — Production Database Context");
w();
w("| | |");
w("|---|---|");
w("| **Generated** | {$generatedAt} |");
w("| **Database** | `{$DB_NAME}` on `{$DB_HOST}` |");
w("| **MySQL version** | {$mysqlVersion} |");
w("| **Tables** | {$totalTables} |");
if ($totalExact !== null) {
    w("| **Total rows (exact)** | " . fmt($totalExact) . " |");
} else {
    w("| **Total rows (estimate)** | " . fmt($totalEst) . " |");
}
w("| **Data size** | " . human_bytes($totalData) . " |");
w("| **Index size** | " . human_bytes($totalIndex) . " |");
w("| **Row count method** | " . ($EXACT_ROW_COUNTS ? 'exact `COUNT(*)`' : 'information_schema estimate') . " |");
w();

// ── Table Inventory ───────────────────────────────────────────────────────────

w("## Table Inventory");
w();
w("Sorted alphabetically. Click any table name in the [Table Details](#table-details) section for columns and indexes.");
w();
w("| # | Table | Engine | Rows | Data | Indexes | Auto-Inc | Cols |");
w("|---|-------|--------|-----:|-----:|--------:|---------:|-----:|");

$emptyTables = [];

foreach ($allTables as $i => $t) {
    $tbl     = $t['TABLE_NAME'];
    $rows    = $EXACT_ROW_COUNTS ? $exactCounts[$tbl] : (int) $t['TABLE_ROWS'];
    $data    = human_bytes((int) $t['DATA_LENGTH']);
    $idx     = human_bytes((int) $t['INDEX_LENGTH']);
    $engine  = $t['ENGINE'] ?? '—';
    $ai      = $t['AUTO_INCREMENT'] !== null ? fmt((int) $t['AUTO_INCREMENT']) : '—';
    $cols    = (int) $t['COLUMN_COUNT'];
    $anchor  = strtolower(preg_replace('/[^a-z0-9]+/', '-', $tbl));

    w("| " . ($i + 1) . " | [`{$tbl}`](#{$anchor}) | {$engine} | " . fmt($rows) . " | {$data} | {$idx} | {$ai} | {$cols} |");

    if ($rows === 0) {
        $emptyTables[] = $tbl;
    }
}
w();

// ── Empty tables ──────────────────────────────────────────────────────────────

if (!empty($emptyTables)) {
    w("## Tables With Zero Rows");
    w();
    w("These tables exist in the schema but currently hold no data. Possible reasons: staging tables, disabled features, or recently truncated archive tables.");
    w();
    foreach ($emptyTables as $tbl) {
        w("- `{$tbl}`");
    }
    w();
}

// ── Cross-cutting schema patterns ─────────────────────────────────────────────

err("Analysing schema patterns …");

// Columns that appear in many tables (schema conventions)
$patternCols = [
    'is_deleted'   => [],
    'created_at'   => [],
    'updated_at'   => [],
    'status'       => [],
    'int_id'       => [],   // FK to pi_intimations
    'insurer_id'   => [],   // FK to pi_insurer
    'ro_id'        => [],   // FK to pi_ro
];

$patternStmt = $pdo->prepare("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = ?
      AND COLUMN_NAME IN ('is_deleted','created_at','updated_at','status','int_id','insurer_id','ro_id')
    ORDER BY COLUMN_NAME, TABLE_NAME
");
$patternStmt->execute([$DB_NAME]);
foreach ($patternStmt->fetchAll() as $row) {
    $col = $row['COLUMN_NAME'];
    if (isset($patternCols[$col])) {
        $patternCols[$col][] = $row['TABLE_NAME'];
    }
}

// Apply prefix filter to pattern results
if (!empty($TABLE_PREFIX_FILTER)) {
    foreach ($patternCols as $col => $tables) {
        $patternCols[$col] = array_values(array_filter(
            $tables,
            function (string $tbl) use ($TABLE_PREFIX_FILTER): bool {
                foreach ($TABLE_PREFIX_FILTER as $prefix) {
                    if (strpos($tbl, $prefix) === 0) return true;
                }
                return false;
            }
        ));
    }
}

w("## Schema Patterns");
w();
w("Common columns that appear across multiple tables — these reveal the conventions used throughout the codebase.");
w();
w("| Column | Tables that have it | Count |");
w("|--------|---------------------|------:|");
foreach ($patternCols as $col => $tables) {
    if (empty($tables)) continue;
    $list = implode(', ', array_map(function (string $t): string { return "`{$t}`"; }, $tables));
    w("| `{$col}` | {$list} | " . count($tables) . " |");
}
w();

// ── Tables with declared FK constraints (MySQL CONSTRAINT FK) ─────────────────

$fkSummaryStmt = $pdo->prepare("
    SELECT
        kcu.TABLE_NAME,
        kcu.COLUMN_NAME,
        kcu.REFERENCED_TABLE_NAME,
        kcu.REFERENCED_COLUMN_NAME,
        kcu.CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE kcu
    JOIN information_schema.TABLE_CONSTRAINTS tc
      ON tc.CONSTRAINT_NAME  = kcu.CONSTRAINT_NAME
     AND tc.TABLE_SCHEMA     = kcu.TABLE_SCHEMA
     AND tc.TABLE_NAME       = kcu.TABLE_NAME
     AND tc.CONSTRAINT_TYPE  = 'FOREIGN KEY'
    WHERE kcu.TABLE_SCHEMA = ?
      AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME
");
$fkSummaryStmt->execute([$DB_NAME]);
$allFKs = $fkSummaryStmt->fetchAll();

if (!empty($allFKs)) {
    w("## Declared Foreign Key Constraints");
    w();
    w("These FK constraints are enforced at the MySQL engine level (InnoDB).");
    w();
    w("| Table | Column | References | Constraint Name |");
    w("|-------|--------|------------|-----------------|");
    foreach ($allFKs as $fk) {
        $ref = "`{$fk['REFERENCED_TABLE_NAME']}`.`{$fk['REFERENCED_COLUMN_NAME']}`";
        w("| `{$fk['TABLE_NAME']}` | `{$fk['COLUMN_NAME']}` | {$ref} | `{$fk['CONSTRAINT_NAME']}` |");
    }
    w();
} else {
    w("## Declared Foreign Key Constraints");
    w();
    w("> No MySQL-level `FOREIGN KEY` constraints found in this database. All table relationships are enforced at the application layer only.");
    w();
}

// ── Per-table details ─────────────────────────────────────────────────────────

w("---");
w();
w("## Table Details");
w();

foreach ($allTables as $t) {
    $tbl       = $t['TABLE_NAME'];
    $rows      = $EXACT_ROW_COUNTS ? $exactCounts[$tbl] : (int) $t['TABLE_ROWS'];
    $engine    = $t['ENGINE'] ?? '—';
    $collation = $t['TABLE_COLLATION'] ?? '—';
    $comment   = trim((string) ($t['TABLE_COMMENT'] ?? ''));
    $ai        = $t['AUTO_INCREMENT'];

    err("  Writing: `{$tbl}`");

    $anchor = strtolower(preg_replace('/[^a-z0-9]+/', '-', $tbl));
    w("### `{$tbl}` {#{$anchor}}");
    w();

    // Stats line
    $stats = "**Engine:** {$engine}"
           . " &nbsp;|&nbsp; **Rows:** " . fmt($rows)
           . ($ai !== null ? " &nbsp;|&nbsp; **Auto-increment at:** " . fmt((int) $ai) : '')
           . " &nbsp;|&nbsp; **Data:** " . human_bytes((int) $t['DATA_LENGTH'])
           . " &nbsp;|&nbsp; **Indexes:** " . human_bytes((int) $t['INDEX_LENGTH'])
           . " &nbsp;|&nbsp; **Collation:** " . $collation;
    w($stats);

    if ($comment !== '' && strtolower($comment) !== 'view') {
        w();
        w("**Table comment:** " . md($comment));
    }
    w();

    // ── Columns ───────────────────────────────────────────────

    $colStmt = $pdo->prepare("
        SELECT
            ORDINAL_POSITION,
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            EXTRA,
            COLUMN_KEY,
            COLUMN_COMMENT
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME   = ?
        ORDER BY ORDINAL_POSITION
    ");
    $colStmt->execute([$DB_NAME, $tbl]);
    $columns = $colStmt->fetchAll();

    w("**Columns** (" . count($columns) . ")");
    w();
    w("| # | Column | Type | Null | Default | Key | Extra | Comment |");
    w("|--:|--------|------|:----:|---------|:---:|-------|---------|");

    foreach ($columns as $col) {
        $pos     = $col['ORDINAL_POSITION'];
        $name    = md($col['COLUMN_NAME']);
        $type    = md($col['COLUMN_TYPE']);
        $null    = $col['IS_NULLABLE'] === 'YES' ? 'Y' : '';
        $key     = md($col['COLUMN_KEY'] ?? '');
        $extra   = md($col['EXTRA'] ?? '');
        $cmt     = md($col['COLUMN_COMMENT'] ?? '');

        // Default: distinguish NULL value from no default
        if ($col['COLUMN_DEFAULT'] === null && $col['IS_NULLABLE'] === 'YES') {
            $default = 'NULL';
        } elseif ($col['COLUMN_DEFAULT'] === null) {
            $default = '—';
        } else {
            $default = md($col['COLUMN_DEFAULT']);
            if (strlen($default) > 30) $default = substr($default, 0, 27) . '…';
        }

        w("| {$pos} | `{$name}` | `{$type}` | {$null} | `{$default}` | {$key} | {$extra} | {$cmt} |");
    }
    w();

    // ── Indexes ───────────────────────────────────────────────

    $idxStmt = $pdo->prepare("
        SELECT
            INDEX_NAME,
            NON_UNIQUE,
            SEQ_IN_INDEX,
            COLUMN_NAME,
            INDEX_TYPE,
            SUB_PART,
            NULLABLE,
            EXPRESSION
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME   = ?
        ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ");
    $idxStmt->execute([$DB_NAME, $tbl]);
    $rawIdx = $idxStmt->fetchAll();

    $indexes = [];
    foreach ($rawIdx as $r) {
        $n = $r['INDEX_NAME'];
        if (!isset($indexes[$n])) {
            $indexes[$n] = [
                'unique'   => $r['NON_UNIQUE'] == '0' ? 'YES' : '',
                'type'     => $r['INDEX_TYPE'],
                'cols'     => [],
            ];
        }
        // For functional indexes, EXPRESSION is set instead of COLUMN_NAME
        $colEntry = $r['EXPRESSION'] !== null
            ? '(' . $r['EXPRESSION'] . ')'
            : $r['COLUMN_NAME'] . ($r['SUB_PART'] ? '(' . $r['SUB_PART'] . ')' : '');
        $indexes[$n]['cols'][] = $colEntry;
    }

    if (!empty($indexes)) {
        w("**Indexes** (" . count($indexes) . ")");
        w();
        w("| Name | Unique | Type | Columns |");
        w("|------|:------:|------|---------|");
        foreach ($indexes as $name => $idx) {
            $cols = implode(', ', $idx['cols']);
            w("| `" . md($name) . "` | {$idx['unique']} | {$idx['type']} | `" . md($cols) . "` |");
        }
        w();
    }

    // ── FK constraints on this table ──────────────────────────

    $fkStmt = $pdo->prepare("
        SELECT
            kcu.CONSTRAINT_NAME,
            kcu.COLUMN_NAME,
            kcu.REFERENCED_TABLE_NAME,
            kcu.REFERENCED_COLUMN_NAME,
            rc.UPDATE_RULE,
            rc.DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE kcu
        JOIN information_schema.TABLE_CONSTRAINTS tc
          ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
         AND tc.TABLE_SCHEMA    = kcu.TABLE_SCHEMA
         AND tc.TABLE_NAME      = kcu.TABLE_NAME
         AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
        JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
          ON rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
         AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
        WHERE kcu.TABLE_SCHEMA           = ?
          AND kcu.TABLE_NAME             = ?
          AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
    ");
    $fkStmt->execute([$DB_NAME, $tbl]);
    $fks = $fkStmt->fetchAll();

    if (!empty($fks)) {
        w("**Foreign Key Constraints**");
        w();
        w("| Constraint | Column | References | On Update | On Delete |");
        w("|------------|--------|------------|-----------|-----------|");
        foreach ($fks as $fk) {
            $ref = "`{$fk['REFERENCED_TABLE_NAME']}`.`{$fk['REFERENCED_COLUMN_NAME']}`";
            w("| `{$fk['CONSTRAINT_NAME']}` | `{$fk['COLUMN_NAME']}` | {$ref} | {$fk['UPDATE_RULE']} | {$fk['DELETE_RULE']} |");
        }
        w();
    }

    // ── DDL (collapsed) ───────────────────────────────────────

    $ddlRow = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch();
    $ddl    = $ddlRow['Create Table'] ?? '';

    w("<details>");
    w("<summary>DDL</summary>");
    w();
    w("```sql");
    w($ddl . ";");
    w("```");
    w();
    w("</details>");
    w();
    w("---");
    w();
}

// ── Done ──────────────────────────────────────────────────────────────────────

fclose($fh);
err("");
err("Done.");
err("Output written to: {$OUTPUT_FILE}");
err("File size: " . human_bytes(filesize($OUTPUT_FILE)));
