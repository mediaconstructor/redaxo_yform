<?php

/**
 * yform.
 *
 * @author jan.kristinus[at]redaxo[dot]org Jan Kristinus
 * @author <a href="http://www.yakamara.de">www.yakamara.de</a>
 */

echo rex_view::title(rex_i18n::msg('yform'));
$_csrf_key = 'table_migrate';

$page = rex_request('page', 'string', '');

$dbConfigs = rex_yform::getDatabaseConfigurations();
/** @var array<int,array{db: int, table: string}> $available_tables */
$available_tables = [];
foreach ($dbConfigs as $dbId => $dbConfig) {
    $available_tables = array_merge_recursive(
        $available_tables,
        array_map(
            fn($tableName) => [
                'db'    => $dbId,
                'table' => $tableName,
            ],
            rex_sql::factory($dbId)->getTablesAndViews()
        )
    );
}

$yform_tables = [];
/** @var array<int,array{db: int, table: string}> $missing_tables */
$missing_tables = [];

foreach (rex_yform_manager_table::getAll() as $g_table) {
    $yform_tables[] = $g_table->getTableName();
}

foreach ($available_tables as $a_table) {
    if (!in_array($a_table['table'], $yform_tables)) {
        $missing_tables[$a_table['table']] = $a_table;
    }
}
$missingTableChoices = [];
$doPrefixDb = count($dbConfigs) > 1;
foreach ($missing_tables as $missingTable) {
    $missingTableChoices[$missingTable['table']] = $doPrefixDb
        ? "DB{$missingTable['db']}: {$missingTable['table']}"
        : $missingTable['table'];
}
asort($missingTableChoices);

$yform = new rex_yform();
$yform->setObjectparams('form_showformafterupdate', 1);
$yform->setObjectparams('form_name', $_csrf_key);
$yform->setHiddenField('page', $page);
$yform->setValueField('choice', ['name' => 'table_name', 'label' => rex_i18n::msg('yform_table'), 'choices' => $missingTableChoices]);
$yform->setValueField('checkbox', ['schema_overwrite', rex_i18n::msg('yform_manager_table_schema_overwrite')]);
$form = $yform->getForm();

if ($yform->objparams['actions_executed']) {
    $table_name = (string) $yform->objparams['value_pool']['sql']['table_name'];
    $schema_overwrite = (int) $yform->objparams['value_pool']['sql']['schema_overwrite'];
    $selectedTable = array_filter($missing_tables, fn($missingTable) => $missingTable['table'] === $table_name);
    $databaseId = array_shift($selectedTable)['db'] ?? 1;

    try {
        rex_yform_manager_table_api::migrateTable(
            $table_name,
            (0 == $schema_overwrite) ? false : true, // with convert id / auto_increment finder
            $databaseId
        );
        echo rex_view::success(rex_i18n::msg('yform_manager_table_migrated_success'));

        unset($missing_tables[$table_name]);

        $yform = new rex_yform();
        $yform->setObjectparams('form_showformafterupdate', 1);
        $yform->setHiddenField('page', $page);
        $yform->setValueField('choice', ['name' => 'table_name', 'label' => rex_i18n::msg('yform_table'), 'choices' => $missingTableChoices]);
        $yform->setValueField('checkbox', ['schema_overwrite', rex_i18n::msg('yform_manager_table_schema_overwrite')]);
        $form = $yform->getForm();
    } catch (Exception $e) {
        echo rex_view::warning(rex_i18n::msg('yform_manager_table_migrated_failed', $table_name, $e->getMessage()));
    }
}

echo rex_view::info(rex_i18n::msg('yform_manager_table_migrate_info'));

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('yform_manager_table_migrate'));
$fragment->setVar('body', $form, false);
// $fragment->setVar('buttons', $buttons, false);
$form = $fragment->parse('core/page/section.php');

echo $form;
