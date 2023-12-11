<?php



// SELECT <fields> FROM <table> WHERE <conditions>
//REQ --- conditions
//TODO FOR SELECT:
//1. GROUP BY, LIMIT, HAVING

// DELETE FROM <table> WHERE <condition>
//REQ --- conditions

// UPDATE <table> SET ...(<field> = <value>) WHERE <condition>
//REQ --- fields and values arrays(same length), conditions


// INSERT INTO <table> ...<field> VALUES ...<value>
//REQ --- fields and values arrays(same length), conditions




///// Variables above are just for test phase, will be removed once class is in production
class ORM
{

    private $conn = null;
    private $table = null;
    private $tableStructure = null;
    private $errors = [];
    private $query = null;
    private $query_attributes = [];
    private $crud_type = null;
    private $result = null;

    public function __construct(array $db_config)
    {
        $this->setConn($db_config);
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

    public function toRawSql(): string
    {
        $this->query = '';

        $this->buildQuery();
        if (!empty($this->errors)) {
            return '';
        }
        return $this->query;
    }

    public function exectue(): array
    {
        $this->query = '';

        $this->buildQuery();
        if (!empty($this->errors)) {
            return '';
        }


        if ($this->crud_type == 'select') {
            $this->executeSelectQuery();
        } elseif ($this->crud_type == 'select') {
            // $this->executeIUDQuery();
        } else {
            $this->errors[] = 'one of following functions must me called: select(), insert(), update() or delete()';
        }

        return $this->result;
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


    public function setTableStructure(): void
    {
        $sql = "EXPLAIN " . $this->table . ";";
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
            $this->conn = new \mysqli($db_config["DB_HOST"], $db_config["DB_USER"], $db_config["DB_PASSWORD"], $db_config["DB_NAME"]);
            if ($this->conn->connect_error) {
                $this->errors[] = "could not connect to db " . $this->conn->connect_error . " FUNCTION: " . __FUNCTION__ . " LINE: " . __LINE__;
            }
        }
        if (empty($this->table)) {
            $this->table = $db_config['TABLE_NAME'];
        }
    }

    public function c(string $field, string $operator, $value): ORM
    {
        $this->query_attributes[] = ['type' => ($this->hactive() ? 'having_condition' : 'condition'), 'field' => $field, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    public function having(): ORM
    {
        $this->query_attributes[] = ['type'=>'having'];
        return $this;
    }

    public function select(): ORM
    {
        $this->crud_type = 'select';
        return $this;
    }

    public function and(): ORM
    {
        $this->query_attributes[] = ['type' => ($this->hactive() ? 'having_operator' : 'operator'), 'value' => 'AND'];
        return $this;
    }
    public function or(): ORM
    {
        $this->query_attributes[] = ['type' => ($this->hactive() ? 'having_operator' : 'operator'), 'value' => 'OR'];
        return $this;
    }

    public function obr(): ORM
    {
        $this->query_attributes[] = ['type' => ($this->hactive() ? 'having_bracket' : 'bracket'), 'value' => '('];
        return $this;
    }

    public function cbr(): ORM
    {
        $this->query_attributes[] = ['type' => ($this->hactive() ? 'having_bracket' : 'bracket'), 'value' => ')'];
        return $this;
    }

    public function fields(string ...$fields): ORM
    {
        if (count($fields) <= 0) {
            $this->query_attributes[] = ['type' => 'fields', 'value' => ['*']];
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

    private function buildQuery(): void
    {
        if ($this->crud_type == 'select') {

            $this->query .= $this->buildFieldsPartSelect();
            $this->query .= $this->buildWherePart();
            $this->query .= $this->buildGroupByPart();
            $this->query .= $this->buildHavingPart();
            $this->query .=  $this->buildOrderByPart();

        } elseif ($this->crud_type == 'insert') {
        } elseif ($this->crud_type == 'update') {
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
                    $where_part .= ' ' . '`' . $qa['field'] . '` ' . $qa['operator'] . " '" . $qa['value'] . "' ";
                    break;
                default:
                    # code...
                    break;
            }
        }
        return  " WHERE " . $where_part;
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
        return $fields_string == "" ? ('SELECT ' . "*" . ' FROM ') : ('SELECT ' . trim($fields_string, ",") . ' FROM ' .  $this->table);
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
                foreach($qa['fields'] as $field){
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
                    $having_part_string .= ' ' . '`' . $qa['field'] . '` ' . $qa['operator'] . " '" . $qa['value'] . "' ";
                    break;
                default:
                    # code...
                    break;
            }
        }

        return  empty($having_part_string) ? ' ' : " HAVING " . $having_part_string;
    }

    private function hactive(): bool{
        foreach($this->query_attributes as $qa){
            if($qa['type'] == 'having'){
                return true;
            }
        }
        return false;
    }
}
