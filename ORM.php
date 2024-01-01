<?php

define('ORM_PRINT_AND_EXECUTE', 1);
define('ORM_NO_OPTIONS', -1);
define('ORM_DEFAULT_SCHEMA', 'pyaonsir_yabby_stats');
define(
    'ORM_DEFAULT_CONFIG',
    [
        'DB_HOST' => 'localhost',
        'DB_USER' => 'root',
        'DB_PASSWORD' => '',
    ]
);


class ORM
{

    private $conn = null;
    private $schema = null;
    private $table = null;
    private $tableStructure = null;
    private $errors = [];
    private $query = null;
    private $query_attributes = [];
    private $crud_type = null;
    private $result = null;


    public function _construct(string $table, string $schema = ORM_DEFAULT_SCHEMA, array $db_config = [])
    {
        $this->setConn($db_config);
        $this->setSchema($schema);
        $this->setTable($table);
        $this->setTableStructure();
    }

    public function getConn()
    {
        return $this->conn;
    }
    public function getConnErrors()
    {
        return ['mysqli' => $this->conn->error_list, 'custom' => $this->errors];
    }

    public function getTableStructure()
    {
        return $this->tableStructure;
    }

    public function clearAll()
    {
        $this->errors = [];
        $this->query = null;
        $this->query_attributes = [];
        $this->crud_type = null;
        $this->result = null;
    }

    private function setSchema(string $schema)
    {
        $sql = "SHOW DATABASES LIKE '$schema';";
        $result = $this->conn->query($sql);
        if ($result->num_rows === 1) {
            $this->schema = $schema;
        } else {
            $this->errors[] = "schema $schema does not exist";
        }
    }

    private function setTable(string $table)
    {
        $sql = "SELECT 1 FROM " . $this->schema . "." . $table . " LIMIT 1 ";
        $result = $this->conn->query($sql);
        if ($result->num_rows === 1) {
            $this->table = $table;
        } else {
            $this->errors[] = "table $table does not exist";
        }
    }

    public function toRawSql(): string
    {
        $this->query = '';
        $this->buildQuery();
        if (!empty($this->errors)) {
            return '';
        }
        return $this->query;
    }

    public function execute(int $flag = ORM_NO_OPTIONS)
    {
        $this->query = '';
        $this->buildQuery();
        if (!empty($this->errors)) {
            return '';
        }

        if ($flag === ORM_PRINT_AND_EXECUTE) {
            echo "<br>" . $this->query . "<br>";
        }

        if ($this->crud_type == 'select') {
            $this->executeSelectQuery();
        } elseif (in_array($this->crud_type, ['update', 'delete'])) {
            $this->executeIUDQuery();
        } else {
            $this->errors[] = 'One of following functions must me called: select(), insert(), update() or delete()';
        }
        $tmp_result = $this->result;
        $this->clearAll();
        return $tmp_result;
    }

    public function executeSelectQuery(): void
    {
        if (!empty($this->query)) {
            $rs = $this->conn->query($this->query);
            $arr = [];
            if (isset($rs->num_rows) && $rs->num_rows > 0) {
                while ($row = $rs->fetch_assoc()) {
                    $arr[] = $row;
                }
            }
            $this->result = $arr;
        }
    }
    public function executeIUDQuery(): void
    {
        if (!empty($this->query)) {
            $this->result = ($this->conn->query($this->query) === TRUE);
        }
    }
    public function setTableStructure(): void
    {
        $sql = "EXPLAIN " .  $this->schema . '.' . $this->table . ";";
        $rows = [];
        $result = $this->conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $rows[$row['Field']] = $row;
            }
            $this->tableStructure = $rows;
        } else {
            $this->errors[] = "could not explain table or table does not exist" . " FUNCTION: " . __FUNCTION__ . " LINE: " . __LINE__;
        }
    }

    private function setConn(array $db_config): void
    {
        if (empty($this->conn)) {

            if (isset($db_config['CONN'])) {
                $this->conn = $db_config['CONN'];
            } else {
                $this->conn = new \mysqli(
                    ($db_config["DB_HOST"] ?? ORM_DEFAULT_CONFIG['DB_HOST']),
                    ($db_config["DB_USER"] ?? ORM_DEFAULT_CONFIG['DB_USER']),
                    ($db_config["DB_PASSWORD"] ?? ORM_DEFAULT_CONFIG['DB_PASSWORD'])
                );
            }

            if ($this->conn->connect_error) {
                $this->errors[] = "could not connect to db " . $this->conn->connect_error . " FUNCTION: " . __FUNCTION__ . " LINE: " . __LINE__;
            }
        }
    }

    public function c(string $field, string $operator, string $value): ORM
    {
        $value = $this->literalValue($value);

        $this->query_attributes[] = ['type' => ($this->isKeywordActive('having') ? 'having_condition' : 'condition'), 'field' => $field, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    public function having(): ORM
    {
        $this->query_attributes[] = ['type' => 'having'];
        return $this;
    }

    public function limit(int $limit): ORM
    {
        $this->query_attributes[] = ['type' => 'limit', 'value' => $limit];
        return $this;
    }

    public function offset(int $offset): ORM
    {
        $this->query_attributes[] = ['type' => 'offset', 'value' => $offset];
        return $this;
    }

    public function select(): ORM
    {
        $this->crud_type = 'select';
        return $this;
    }

    public function insert(): ORM
    {
        $this->crud_type = 'insert';
        return $this;
    }

    public function ignore(): ORM
    {
        $this->query_attributes[] = ['type' => 'ignore'];
        return $this;
    }

    public function update(): ORM
    {
        $this->crud_type = 'update';
        return $this;
    }

    public function and(): ORM
    {
        $this->query_attributes[] = ['type' => ($this->isKeywordActive('having') ? 'having_operator' : 'operator'), 'value' => 'AND'];
        return $this;
    }
    public function or(): ORM
    {
        $this->query_attributes[] = ['type' => ($this->isKeywordActive('having') ? 'having_operator' : 'operator'), 'value' => 'OR'];
        return $this;
    }

    public function obr(): ORM
    {
        $this->query_attributes[] = ['type' => ($this->isKeywordActive('having') ? 'having_bracket' : 'bracket'), 'value' => '('];
        return $this;
    }

    public function cbr(): ORM
    {
        $this->query_attributes[] = ['type' => ($this->isKeywordActive('having') ? 'having_bracket' : 'bracket'), 'value' => ')'];
        return $this;
    }

    public function fields(string ...$fields): ORM
    {
        if (count($fields) <= 0) {
            $this->query_attributes[] = ['type' => 'fields', 'value' => ['']];
        }
        $all_fields = [];
        foreach ($fields as $field) {
            $all_fields[] = $field;
        }
        $this->query_attributes[] = ['type' => 'fields', 'value' => $all_fields];
        return $this;
    }

    public function orderBy(string $field, string $value): ORM
    {
        $this->query_attributes[] = ['type' => 'order_by', 'field' => $field, 'value' => $value];
        return $this;
    }

    public function groupBy(string ...$fields): ORM
    {
        $this->query_attributes[] = ['type' => 'group_by', 'fields' => $fields];
        return $this;
    }

    public function set(string $field, string $value): ORM
    {

        $value = $this->literalValue($value);
        $this->query_attributes[] = ['type' => 'set', 'field' => $field, 'value' => $value];
        return $this;
    }

    public function values(string ...$fields): ORM
    {
        $this->query_attributes[] = ['type' => 'insert_values', 'fields' => $fields];
        return $this;
    }

    private function buildQuery(): void
    {
        if ($this->crud_type == 'select') {

            $this->query .= $this->buildFieldsPartSelect();
            $this->query .= $this->buildWherePart();
            $this->query .= $this->buildGroupByPart();
            $this->query .= $this->buildHavingPart();
            $this->query .=  $this->buildOrderByPart();
            $this->query .=  $this->buildLimitPart();
            $this->query .=  $this->buildOffsetPart();
        } elseif ($this->crud_type == 'insert') {
            // $this->query .= $this->buildfirstInsertPart();
            // $this->query .= $this->buildFieldsInsertPart();
            // $this->query .= $this->buildValuesInsertPart();

        } elseif ($this->crud_type == 'update') {
            $this->query .= $this->buildfirstUpdatePart();
            $this->query .= $this->buildSetPart();
            $this->query .= $this->buildWherePart();
            $this->query .= $this->buildLimitPart();
            // echo $this->query;
        } elseif ($this->crud_type == 'delete') {
        } else {
            $this->errors[] = 'one of following functions must me called: select(), insert(), update() or delete()';
        }
    }


    private function buildWherePart(): string
    {
        $where_part = "";
        foreach ($this->query_attributes as $qa) {

            switch ($qa['type']) {
                case 'bracket':
                    $where_part .= $qa['value'];
                    break;
                case 'operator':
                    $where_part .= ' ' . $qa['value'] . ' ';
                    break;
                case 'condition':
                    $where_part .= ' ' . $qa['field'] . ' ' . $qa['operator'] . " " . $qa['value'] . " ";
                    break;
                default:
                    # code...
                    break;
            }
        }
        return  " WHERE " . (empty($where_part) ? '0' : $where_part);
    }

    private function buildFieldsPartSelect(): string
    {
        $fields_string = "";
        foreach ($this->query_attributes as $qa) {
            if ($qa['type'] == 'fields') {
                foreach ($qa['value'] as $f) {
                    if ($f != '') {
                        $fields_string .= $f . ',';
                    }
                }
            }
        }
        return $fields_string == "" ? ('SELECT ' . "" . ' FROM ' .  $this->schema . '.' . $this->table) : ('SELECT ' . trim($fields_string, ",") . ' FROM ' .  $this->schema . '.' . $this->table);
    }

    private function buildOrderByPart(): string
    {
        $order_by_string = "";
        foreach ($this->query_attributes as $qa) {

            if ($qa['type'] == 'order_by') {
                $order_by_string .= $qa['field'] . ' ' . $qa['value'] . ',';
            }
        }
        return $order_by_string == "" ? "" : ' ORDER BY ' . trim($order_by_string, ",");
    }

    private function buildGroupByPart(): string
    {
        $group_by_string = "";
        foreach ($this->query_attributes as $qa) {

            if ($qa['type'] == 'group_by') {
                foreach ($qa['fields'] as $field) {
                    $group_by_string .= $field . ',';
                }
            }
        }
        return $group_by_string == "" ? "" : ' GROUP BY ' . trim($group_by_string, ",");
    }

    private function buildHavingPart(): string
    {

        $having_part_string = "";

        foreach ($this->query_attributes as $qa) {

            switch ($qa['type']) {
                case 'having_bracket':
                    $having_part_string .= $qa['value'];
                    break;
                case 'having_operator':
                    $having_part_string .= ' ' . $qa['value'] . ' ';
                    break;
                case 'having_condition':
                    $having_part_string .= ' ' . '' . $qa['field'] . ' ' . $qa['operator'] . " '" . $qa['value'] . "' ";
                    break;
                default:
                    # code...
                    break;
            }
        }

        return  empty($having_part_string) ? ' ' : " HAVING " . $having_part_string;
    }

    private function buildLimitPart(): string
    {
        $limit_string = "";
        foreach ($this->query_attributes as $qa) {

            if ($qa['type'] == 'limit') {
                $limit_string = 'LIMIT ' . $qa['value'];
            }
        }
        return $limit_string == "" ? "" : ' ' . trim($limit_string);
    }

    private function buildOffsetPart(): string
    {
        $limit_string = "";
        foreach ($this->query_attributes as $qa) {

            if ($qa['type'] == 'offset') {
                $limit_string .= 'OFFSET ' . $qa['value'];
            }
        }
        return $limit_string == "" ? "" : ' ' . trim($limit_string);
    }

    private function buildfirstUpdatePart(): string
    {
        return "UPDATE " . $this->schema . '.' . $this->table . ' SET ';
    }

    private function buildSetPart(): string
    {
        $set_string = "";
        foreach ($this->query_attributes as $qa) {
            if ($qa['type'] == 'set') {
                $set_string .= $qa['field'] . ' = ' . $qa['value'] . ',';
            }
        }
        return trim($set_string, ',');
    }

    /// PRIVATE HELPERS USED IN CLASS
    private function isKeywordActive(string $keyword): bool
    {
        foreach ($this->query_attributes as $qa) {
            if ($qa['type'] == "$keyword") {
                return true;
            }
        }
        return false;
    }

    private function literalValue($val)
    {
        if (substr($val, 0, 1) == '') {
            return "" . ltrim($val, '') . "";
        } else {
            return "'" . $val . "'";
        }
    }

    public function getFieldsRequiredForInsert(): array
    {
        $required = [];
        $fields = $this->getTableStructure();
        foreach ($fields as $name => $val_array) {
            if ($val_array['Null'] == 'NO' && !$this->str_contains_local($val_array['Extra'], 'auto_increment')) {
                $required[] = $name;
            }
        }
        return $required;
    }


    private function str_contains_local($haystack, $needle)
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
    /// PRIVATE HELPERS USED IN CLASS
    /// PUBLIC HELPERS USED OUTSIDE CLASS
    public function in(array $arr, string $array_key = '')
    {
        $in_string = "";

        foreach ($arr as $el) {
            if ($array_key == '') {
                $in_string .= "'" . $el . "',";
            } else {
                $in_string .= "'" . ($el[$array_key] ?? '') . "',";
            }
        }

        return "~(" . trim($in_string, ",") . ")";
    }
}
