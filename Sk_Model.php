<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * model的基本实现,提供一些model的标准和规范,实现了一些基础通用的操作.
 *
 * @package		Core
 * @subpackage	Sk_Model
 * @author shijianhang
 */
class Sk_Model extends CI_Model
{

	/**
	 * 用于存储错误信息.
	 *
	 * @var string
	 * @access public
	 */
	public $error = '';

	/**
	 * 表名
	 *
	 * @var string
	 * @access protected
	 */
	public $table = '';

	/**
	 * 数据表主键,如果没有默认使用"id".
	 *
	 * @var string
	 * @access protected
	 */
	public $key = 'id';

    /**
     * 表字段
     * @var array
     */
	protected $fields = NULL;

	/**
	 * 数据库连接配置 (string or array)
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $db_con = '';

    /**
     * 主验证规则.
     * 
     * @var ArrayObject
     */
    protected $validation_rules = array();

    /**
     * @var array 仅用于插入的额外验证规则
     */
    protected $insert_validation_rules = array();

    /**
     * 是否跳过数据验证. 
     * 默认跳过,可以调用skip_validation()进行设置.
     */
    protected $skip_validation = TRUE;

    /**
     * 有一个的关系
     * @var array
     */
    protected $has_one = array ();

    /**
     * 从属于的关系
     * @var array
     */
    protected $belongs_to = array ();

    /**
     * 有多个的关系
     * @var array
     */
    protected $has_many = array ();

    /**
     * 是否在联查
     * @var bool
     */
    protected $withing = false;

    /**
     * 要联查has_many的关联对象
     * @var array
     */
    protected $with_many = array ();

    //----------------------------- 初始化 ---------------------------------------
	/**
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->ini_dbcon();
	}
	
	public function ini_dbcon()
	{
		// 另外设置的数据库连接
		if ( ! empty($this->db_con))
			$this->db = $this->load->database($this->db_con, TRUE);

		if ( ! isset($this->db))
			$this->load->database();
	}

    /**
     * 给字段加表前缀
     * @param $field
     * @return
     */
    public function table_prefix($field){
        if(strpos($field, '.') !== FALSE) // 自身带表前缀
            return $field;

        // 加表前缀
        return $this->table . '.' . $field;
    }

    //------------------------------- 修改选项 -------------------------------------
    /**
     * 设置数据库链接
     *
     * @param string $db_con
     */
    public function set_db_con($db_con)
    {
        if (class_exists('CI_DB') AND isset($this->db))
            $this->db->close();

        $this->db = $this->load->database($db_con, TRUE);
    }

    /**
     * 设置是否跳过数据验证
     *
     * @param bool $skip (可选) 默认跳过数据验证
     *
     * @return object    returns $this 用于链式操作
     */
    public function skip_validation($skip = TRUE)
    {
        $this->skip_validation = $skip;
        return $this;
    }

	//------------------------------查询 ---------------------------------
    /**
     * 返回表所有字段
     * @param string $table 表名
     * @return array.
     */
    public function list_fields($table='')
    {
        if($this->fields === NULL)
            $this->fields = $this->db->list_fields($table == '' ? $this->table : $table);

        return $this->fields;
    }

	/**
	 * 根据主键查询一条数据.
	 * 
	 * 必须保证主键key已经设置
	 *
	 * @param string $id 主键记录.
	 *
	 * @return array OR FALSE.
	 */
	public function find($id = NULL)
	{
		$query = $this->db;
		if($id !== NULL)
		  $query->where($this->table_prefix($this->key), $id);
        $result = $query->limit(1)->get($this->table);
		if ( ! $result->num_rows())
			return FALSE;

		$row = $result->row_array();
		$row = $this->_transform_row($row);

		$this->_clear_with();
		return $row;
	}

	/**
	 * 从数据表查询多条数据.
	 *
	 * @return array OR FALSE.
	 */
	public function find_all()
	{
		$query = $this->db->get($this->table);
		if (!$query->num_rows())
			return array();

		$result = $query->result_array();
		foreach($result as &$row)
		    $row = $this->_transform_row($row);

        $this->_clear_with();
        return $result;

	}

	/**
	 * 根据给定的条件查询多条数据.
	 *
	 * @param mixed  $field	查询的字段也是包含字段和查询值的数组.
	 * @param mixed  $value (可选)查询的条件值.
	 * @param string $type  条件类型 'and' or 'or'.
	 *
	 * @return array OR FALSE.
	 */
	public function find_all_by($field, $value = NULL, $type = 'and')
	{
		// 设置为数组
		if ( ! is_array($field))
			$field = array($field => $value);

		if (strtolower($type) == 'or')
			$this->db->or_where($field);
		else
			$this->db->where($field);

		return $this->find_all();
		
	}

	/**
	 * 根据条件获取符合条件的第一条数据.
	 * @param mixed  $field	查询的字段也是包含字段和查询值的数组.
	 * @param mixed  $value (可选)查询的条件值.
	 * @param string $type  条件类型 'and' or 'or'.
	 *
	 * @return array OR FALSE.
	 */
	public function find_by($field, $value = '', $type = 'and')
	{
		if (empty($field) || ( ! is_array($field) && empty($value)))
		{
			$this->error = '没有足够的条件查询数据';
			$this->_logit('['. get_class($this) .': '. __METHOD__ .'] ' . $this->error);
			return FALSE;
		}
		
		if ( ! is_array($field))
			$field = array($field => $value);

		if (strtolower($type) == 'or')
			$this->db->or_where($field);
		else
			$this->db->where($field);

		$this->db->limit(1);
        $rows = $this->find_all();
        return empty($rows) ? false : $rows[0];
	}

    //-------------------------------关联查询-------------------------------------
    /**
     * 获得关联模型
     * @param $alias
     * @return bool
     */
    protected function _related_model($alias) {
        $model = false;
        $types = array('has_one', 'belongs_to', 'has_many');
        foreach($types as $type){
            if (isset ( $this->{$type} [$alias] ))
                $model = $this->{$type} [$alias] ['model'];
        }
        if($model) {
            $this->load->model( $model );
            return $this->$model;
        }

        return false;
    }

    /**
     * 转换查询结果的一行数据
     * @param array $values Values to load
     * @return array
     */
    protected function _transform_row(array $values) {
        $object = array();

        // 注: 关联对象只支持一层
        foreach ( $values as $column => $value ) {
            if (strpos ( $column, ':' ) === FALSE) { // 当前模型属性
                $object [$column] = $value;
            } else { // 关联模型属性
                list ( $prefix, $column ) = explode ( ':', $column, 2 ); // 拆分模型名 + 属性名
                $object [$prefix] [$column] = $value; // Collect related object's properties
            }
        }

        // TODO: 联查放大问题
        foreach($this->with_many as $alias){
            $id = $object[$this->key];
            $object[$alias] = $this->find_all_related($id, $alias);
        }

        return $object;
    }

    /**
     * 清空联查的变量
     */
    protected function _clear_with(){
        $this->withing = false;
        $this->with_many = array();
    }

    /**
     * 联查一对一的关联对象
     * @param string $alias 联查关联对象名
     * @param string $columns 联查的字段名, 如 'uid,uname,mobile', 如果为空则查全部字段
     * @return
     */
    public function with($alias, $columns = '') {
        if($this->withing == false) {
            // 查询当前对象的字段
            if(empty($this->db->ar_select)) // 如果没有显式设置查询字段，则查询全部
                $this->select($this->table.'.*');
            else
                $this->db->ar_select = array_map(array($this, 'table_prefix'), $this->db->ar_select);
            $this->withing = true;
        }

        // 1 一对多
        if(isset ( $this->has_many [$alias])){
            $this->with_many[] = $alias;
            return $this;
        }

        // 2 一对一
        // 获得关联模型
        $related = $this->_related_model($alias);
        if(!$related)
            throw new Exception("不存在关联关系: $alias");

        // 联查关联对象的字段
        if($columns == '')
            $columns = $related->list_fields();
        else
            $columns = preg_split('/[,\s]/', $columns);
        foreach ($columns as $column) {
            $name = $alias . '.' . $column;
            $alias2 = $alias . ':' . $column;

            // Add the prefix so that load_result can determine the relationship
            $fields[] = "$name AS `$alias2`";
        }
        $this->select ( implode(', ', $fields) );

        // 构造联查条件
        if (isset ( $this->belongs_to [$alias] )) { // belongs_to
            $join_col1 = $alias . '.' . $related->key; // 主表.主键
            $join_col2 = $this->table . '.' . $this->belongs_to [$alias] ['foreign_key']; // 从表.外键
        } else { // has_one
            $join_col1 = $this->table . '.' . $this->key; // 主表.主键
            $join_col2 = $alias . '.' . $this->has_one [$alias] ['foreign_key']; // 从表.外键
        }

        // 联查关联表
        $this->join ($related->table.' '.$alias, $join_col1.'='.$join_col2, 'LEFT' );

        return $this;
    }

    /**
     * 构建关联对象的查询
     * @param $id
     * @param $alias
     * @return CI_DB_active_record
     */
    public function query_related($id, $alias)
    {
        // 获得关联模型
        $model = $this->_related_model($alias);
        if (!$model)
            throw new Exception("不存在关联关系: $alias");

        // 构造联查条件
        if (isset ($this->belongs_to [$alias])) {
            $column = $model->key;
        } elseif (isset ($this->has_one [$alias])) {
            $column = $this->has_one [$alias] ['foreign_key'];
        } elseif (isset ($this->has_many [$alias])) {
            $column = $this->has_many [$alias] ['foreign_key'];
            $orderby = $this->has_many [$alias] ['order'];
            if($orderby)
                $model->order_by($orderby);
        }

        if(is_array($id))
            return $model->where_in($column, $id);

        return $model->where($column, $id);
    }

    /**
     * 查询关联对象
     *
     * @param $id 本对象id
     * @param $alias 关联对象别名
     * @return array OR FALSE.
     */
    public function find_related($id, $alias)
    {
        return $this->query_related($id, $alias)->find();
    }

    /**
     * 查询关联对象
     *
     * @param $id 本对象id
     * @param $alias 关联对象别名
     * @return array OR FALSE.
     */
    public function find_all_related($id, $alias)
    {
        return $this->query_related($id, $alias)->find_all();
    }

    /**
     * 统计关联表的行数.
     *
     * @return int
     */
    public function count_all_related($id, $alias)
    {
        return $this->query_related($id, $alias)->count_all();
    }

	//----------------------------- 增加 ----------------------------------

	/**
	 * 向数据库新增一条数据.
	 *
	 * @param array $data 用于新增到数据库的数据.
	 *
	 * @return bool|mixed 新增的id or FALSE.
	 */
	public function insert($data)
	{
		// 不跳过数据验证
		if ($this->skip_validation === FALSE)
		{
		    $data = $this->validate($data, 'insert');
		    // 数据验证失败
            if ($data === FALSE)
                return FALSE;
		}
		// 前置操作
		$data = $this->trigger('before_insert', $data);

		// Insert it
		$status = $this->db->insert($this->table, $data);

		if ($status == FALSE)
		{
			$this->error = $this->_get_db_error_message();
			return FALSE;
        }

        $id = $this->db->insert_id();
        $this->trigger('after_insert', $id);
        return $id;
        
	}

	/**
	 * 批量新增数据.
	 *
	 * @param array $data 用于新增的数组.
	 *
	 * @return bool
	 */
	public function insert_batch($data)
	{
		foreach ($data as $key => &$record)
			$record = $this->trigger('before_insert', $record);

		// Insert it
		$status = $this->db->insert_batch($this->table, $data);

		if ($status === FALSE)
		{
			$this->error = $this->_get_db_error_message();
			return FALSE;
		}
		
		return TRUE;
	}

	//------------------------------- 更新 --------------------------------

	/**
	 * 更新数据.
	 *
	 * @param mixed	$where	如果是条件不是数组则为是基于primary_key为条件更新数据.
	 * @param array $data	更新的数据.
     *
     * @example $this->model->update($id, $data);
     * @example $this->model->update(array('id' => $id), $data);
	 *
	 * @return bool TRUE/FALSE
	 */
	public function update($where, $data)
	{
		// 数据验证
		if ($this->skip_validation === FALSE)
		{
		    $data = $this->validate($data);
		    if ($data === FALSE)
				return FALSE;
		}

		if ( ! is_array($where))
			$where = array($this->key => $where);

		$data = $this->trigger('before_update', $data);
		
		$result = $this->db->update($this->table, $data, $where);
		
		if ($result)
		{
			$this->trigger('after_update', array($data, $result));
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * 批量更新数据.
	 *
	 * @param array  $data  更新的数据.
	 * @param string $index 键名
	 * @see CI_DB_active_record->update_batch()
	 *
	 * @return bool TRUE/FALSE
	 */
	public function update_batch($data, $index)
	{
        $result = $this->db->update_batch($this->table, $data, $index);
        return empty($result) ? TRUE : FALSE;
        
	}
	
	//-------------------------------删除-------------------------------------
	/**
	 * 删除数据.
	 *
	 * @example $this->model->delete($id);
	 * @example $this->model->delete(array('id' => $id));
	 * 
	 * @param mixed $where (可选) 主键值.
	 *
	 * @return bool TRUE/FALSE
	 */
	public function delete($where = NULL)
	{
		$this->trigger('before_delete', $where);

        if ( ! is_array($where))
            $where = array($this->key => $where);

        $this->db->where($this->key, $where)->delete($this->table);
		
		if ($this->db->affected_rows())
		{
			$this->trigger('after_delete', $where);
			return TRUE;
		}

		$this->error = 'DB Error: ' . $this->_get_db_error_message();

		return FALSE;
		
	}

	/**
	 * 根据指定指定条件删除数据.
	 *
	 * @param mixed/array $data 字符串的条件或者数组
	 *
	 * @example 1) $this->model->delete_where(array( 'key' => 'value', 'key2' => 'value2' ))
	 * @example 2) $this->model->delete_where("`key` = 'value' AND `key2` = 'value2'")
	 *
	 * @return bool TRUE/FALSE
	 */
	public function delete_where($where)
	{
		$where = $this->trigger('before_delete', $where);

		$this->db->where($where);

		$this->db->delete($this->table);

		$result = $this->db->affected_rows();

		if ($result)
		{
			$this->trigger('after_delete', $result);
			return $result;
		}

		$this->error = 'DB Error: ' . $this->_get_db_error_message();

		return FALSE;
		
	}

	//------------------------------- 统计 --------------------------------
	/**
	 * 查询指定条件的数据是否唯一存在.
	 *
	 * @param string $field	用于查询的字段.
	 * @param string $value 匹配$field的值.
	 *
	 * @return bool TRUE/FALSE
	 */
	public function is_unique($field, $value)
	{
		return $this->count_by($field, $value) > 1;
	}

	/**
	 * 返回表中的行数.
	 *
	 * @return int
	 */
	public function count_all()
	{
		return $this->db->count_all_results($this->table);
	}

	/**
	 * 根据条件返回表中的行数.
	 *
	 * @param string/array $field	要查询的字段,可以是数组.
	 * @param string $value			(可选)查询值.
	 *
	 * @example 1) count_by("`key` = 'value' AND `key2` = 'value2'")
	 * @example 2) count_by('key', 'value')
	 * @example 3) count_by(array('key' => 'value', 'key2' => 'value2'))
	 * 
	 * @return bool|int
	 */
	public function count_by($field, $value = NULL)
	{
		if ( (is_string($field) && empty($field)) || (!is_array($field) && empty($value)))
		{
			$this->error = '没有足够的条件来统计结果';
			$this->_logit('['. get_class($this) .': '. __METHOD__ .'] '. $this->error);
			return FALSE;
		}

		if( (is_array($field) && !empty($field)) || (is_string($field) && !empty($field)  && !empty($value)) ){
			$this->db->where($field, $value);
		}


		return $this->db->count_all_results($this->table);
	}

    //---------------------------- 增删改查的前置后置事件 -----------------------------------
    /**
	 * 触发model的特定事件
	 * model中方法的access须定义protected,方法名带"_"前缀,
	 * 如：protected function _before_find($data){...}
	 *
	 * @access public
	 *
	 * @param string 	$event 	要执行的事件
	 * @param mixed 	$data 	需要进行处理的数据.
	 *
	 * @return mixed
	 */
	public function trigger($event, $data = FALSE)
	{
		$method = "_{$event}";
		return $data ? $this->{$method}($data) : $this->{$method}();

	}

	/**
	 * 查询的前置操作(目前没有使用)
	 * 你可以在model中覆盖这个方法,实现自己的操作
	 *
	 * @return void
	 */
	protected function _before_find() {}

	/**
	 * 查询的后置操作(目前没有使用)
	 * 你可以在model中覆盖这个方法,实现自己的操作
	 *
	 * @param array $data 查询得到的结果数据
	 * @return mixed
	 */
	protected function _after_find($data)
	{
		return $data;
	}
	
	/**
	 * 插入的前置操作
	 * 你可以在model中覆盖这个方法,实现自己的操作
	 *
	 * @param array $data 要新增的数据
	 * @return mixed
	 */
	protected function _before_insert($data)
	{
		return $data;
	}

	/**
	 * 插入的后置操作
	 * 你可以在model中覆盖这个方法,实现自己的操作
	 *
	 * 注意：
	 * 如果新增的数据需要返回新增ID,操作调用这个方法
	 * 另：
	 * 批量添加不会调用这个方法
	 *
	 * @param int $id 新增主键ID
	 * @return bool|mixed 可以是新增的ID或者操作失败返回的FALSE
	 */
	protected function _after_insert($id)
	{
		return $id;
	}

	/**
	 * 更新的前置操作
	 * 在数据验证之后调用
	 *
	 * 你可以在model中覆盖这个方法,实现自己的操作
	 *
	 * 注意：
	 * 批量更新不会调用这个方法
	 *
	 * @param array $data 要更新的数据
	 * @return array 处理过的更新数据
	 */
	protected function _before_update($data)
	{
		return $data;
	}

	/**
	 * 更新的后置操作
	 * 你可以在model中覆盖这个方法,实现自己的操作
	 *
	 * 注意：
	 * 批量更新不会调用这个方法
	 *
	 * @param array $data 包含更新的数据和更新处理结果,如：array(更新的数据, 处理结果)
	 * @return void
	 */
	protected function _after_update($data) {} 

	/**
	 * 删除的前置操作
	 * 你可以在model中覆盖这个方法,实现自己的操作
	 *
	 * @param array $id 要更新数据ID
	 * @return void
	 */
	protected function _before_delete($data)
	{
		return $data;
	}
	
	/**
	 * 删除的后置操作
	 * 你可以在model中覆盖这个方法,实现自己的操作
	 *
	 * @param bool|int $result 处理结果,一般是处理结果影响的行数
	 * @return void
	 */
	protected function _after_delete($result) {} 
	
    //--------------------------------校验------------------------------------
    /**
     * 获取模型的验证规则common/core/Sk_Model.phpcommon/core/Sk_Model.phpcommon/core/Sk_Model.php
     *
     * @param String $type	获取规则的类型：'update' or 'insert', 如果是新增insert,会添加定义的额外验证规则($insert_validation_rules)
     *
     * @return array		空的数组或者model的验证规则
     */
    public function get_validation_rules($type = 'update')
    {
        $temp_validation_rules = $this->validation_rules;
        
        if (empty($temp_validation_rules) || ! is_array($temp_validation_rules))
        {
			return array();
        }

        // 如果有额外的insert规则
        if (strtolower($type) == 'insert'
            && is_array($this->insert_validation_rules)
            && ! empty($this->insert_validation_rules)
           )
        {
            // 设置每个验证规则对应的索引位置
            $fieldIndexes = array();
            foreach ($temp_validation_rules as $key => $validation_rule)
            {
                $fieldIndexes[$validation_rule['field']] = $key;
            }

            foreach ($this->insert_validation_rules as $key => $rule)
            {
                if (is_array($rule))
                {
                    $insert_rule = $rule;
                }
                else
                {
                    // 如果$key不是字段名,并且$rule也不数组,那将无法解析这个规则,直接跳过
                    if (is_numeric($key))
                    {
                        continue;
                    }
                    $insert_rule = array(
                        'field' => $key,
                        'rules' => $rule,
                    );
                }

                /*
                 * 如果字段的验证规则已存在与当前的验证规则中,我们更新合并这个证规则
                 * (如果规则不存在就直接替换).
                 */
                if (isset($fieldIndexes[$insert_rule['field']]))
                {
                    $fieldKey = $fieldIndexes[$insert_rule['field']];
                    // 如果该字段的验证规则没有设定
                    if (empty($temp_validation_rules[$fieldKey]['rules']))
                    {
                    	// 直接替换
                        $temp_validation_rules[$fieldKey]['rules'] = $insert_rule['rules'];
                    }
                    else
                    {
                    	// 为这个主规则添加额外的验证规则
                        $temp_validation_rules[$fieldKey]['rules'] .= '|' . $insert_rule['rules'];
                    }
                }
                else
                {
                    // 否则，我们添加了插入规则的验证规则
                    $temp_validation_rules[] = $insert_rule;
                }
            }
        }

        return $temp_validation_rules;
        
    }

	/**
	 * 验证数据.
	 *
	 * 可验证新增和插入的数据,批量操作不进行验证.
	 *
	 * @param  array	$data	用于验证的数据
	 * @param  string	$type	验证的类型：'update' or 'insert'.
	 * @return array/bool       原来的数据 or FALSE
	 */
	public function validate($data, $type = 'update')
    {
        if ($this->skip_validation)
            return $data;

        $current_validation_rules = $this->get_validation_rules($type);

        if (empty($current_validation_rules))
            return $data;

        foreach ($data as $key => $val)
            $_POST[$key] = $val;

        $this->load->library('form_validation');

        if (is_array($current_validation_rules)) {
            $this->form_validation->set_rules($current_validation_rules);
            $valid = $this->form_validation->run();
        } else {
            $valid = $this->form_validation->run($current_validation_rules);
        }

        if ($valid !== TRUE) {
            $this->error = validation_errors();
            return FALSE;
        }

        return $data;
    }

    //----------------------------- 错误与日志 ---------------------------------------
	/**
	 * 获取来与于数据库的错误信息
	 *
	 * @return string
	 */
	protected function _get_db_error_message()
	{
		switch ($this->db->platform())
		{
			case 'mysql':
				return mysql_error($this->db->conn_id);
			case 'mysqli':
				return mysqli_error($this->db->conn_id);
			default:
				return $this->db->_error_message();
		}
		
	}


	/**
	 * 将错误日志记录到文件中.
	 * 
	 * 也可以扩展或者重写这个方法,将错误记录发送到控制台,方便进行进一步的处理.
	 *
	 * @param string $message 要写入的记录.
	 * @param string $level   日志级别,按CI log_message方法.
	 *
	 * @access protected
	 *
	 * @return mixed
	 */
	protected function _logit($message, $level='debug')
	{
		if (empty($message))
		{
			return FALSE;
		}
		
		log_message($level, $message);

	}

	//--------------------------------------------------------------------
    // CI数据库处理的进一步封装
    //--------------------------------------------------------------------
    // 在model中提供CI更多的数据库操作方法
    //
    // 可以这么调用:
    //      $result = $this->model->select('...')
    //                            ->where('...')
    //                            ->having('...')
    //                            ->order_by('...')
    //                            ->get();
    //
    public function select ($select = '*', $escape = NULL) { $this->db->select($select, $escape); return $this; }
    public function select_max ($select = '', $alias = '') { $this->db->select_max($select, $alias); return $this; }
    public function select_min ($select = '', $alias = '') { $this->db->select_min($select, $alias); return $this; }
    public function select_avg ($select = '', $alias = '') { $this->db->select_avg($select, $alias); return $this; }
    public function select_sum ($select = '', $alias = '') { $this->db->select_sum($select, $alias); return $this; }
    public function distinct ($val=TRUE) { $this->db->distinct($val); return $this; }
    public function from ($from) { $this->db->from($from); return $this; }
    public function join($table, $cond, $type = '') { $this->db->join($table, $cond, $type); return $this; }
    public function where($key, $value = NULL, $escape = TRUE) { $this->db->where($key, $value, $escape); return $this; }
    public function or_where($key, $value = NULL, $escape = TRUE) { $this->db->or_where($key, $value, $escape); return $this; }
    public function where_in($key = NULL, $values = NULL) { $this->db->where_in($key, $values); return $this; }
    public function or_where_in($key = NULL, $values = NULL) { $this->db->or_where_in($key, $values); return $this; }
    public function where_not_in($key = NULL, $values = NULL) { $this->db->where_not_in($key, $values); return $this; }
    public function or_where_not_in($key = NULL, $values = NULL) { $this->db->or_where_not_in($key, $values); return $this; }
    public function like($field, $match = '', $side = 'both') { $this->db->like($field, $match, $side); return $this; }
    public function not_like($field, $match = '', $side = 'both') { $this->db->not_like($field, $match, $side); return $this; }
    public function or_like($field, $match = '', $side = 'both') { $this->db->or_like($field, $match, $side); return $this; }
    public function or_not_like($field, $match = '', $side = 'both') { $this->db->or_not_like($field, $match, $side); return $this; }
    public function group_by($by) { $this->db->group_by($by); return $this; }
    public function having($key, $value = '', $escape = TRUE) { $this->db->having($key, $value, $escape); return $this; }
    public function or_having($key, $value = '', $escape = TRUE) { $this->db->or_having($key, $value, $escape); return $this; }
    public function limit($value, $offset = '') { $this->db->limit($value, $offset); return $this; }
    public function offset($offset) { $this->db->offset($offset); return $this; }
    public function set($key, $value = '', $escape = TRUE) { $this->db->set($key, $value, $escape); return $this; }
    public function order_by($orderby, $direction = ''){ $this->db->order_by($orderby, $direction); return $this; }
}