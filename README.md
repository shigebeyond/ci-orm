对象关系映射（Object Relational Mapping 简称ORM）允许你把数据库中的数据当成一个PHP对象来操纵和控制。

一旦你定义了ORM和你的数据库中数据的关系，那么无论你用任何你喜欢的方式操纵数据，以及保存结果到数据库，都不需要使用SQL语言。

通过创建按照配置约定的模型之间的关系，大部分从数据库中重复的编写创建，读取，更新和删除信息的查询可以被减少或者完全消除。

所有的关系都能被自动用ORM库来处理并且你可以像使用标准对象属性一样访问相关数据。

# 定义模型 

## 简单 

如果你的数据库和名称使用默认的约定（数据库使用default，名称使用类名中去掉_model后缀的部分），则模型可以像这样简单的定义：

```
require COMPATH."core/Sk_Model.php";

class User_model extends Sk_Model {
}
```

## 定制 

一些基本模型属性的定义：

```
/**
 * 试客vip(充值余额)模型类
 */
class user_model extends Sk_Model {

    /**
     * 表名称(不带前缀)
     * @var string
     */
    public $table = 'user';

    /**
     * 主键
     * @var int
     */
    public $key = 'uid';

    /**
     * 数据库连接配置 (string or array)
     *
     * @var mixed
     * @access protected
     */
    protected $db_con = 'slave'; // 数据库配置名,如 default 或 slave 或 uc
}
```

# 切换db 

如切换主从库, uc库

```
$this->load->model ( 'user_model' );
//读写分离
$this->user_model->set_db_con('slave');
```

# 读 

使用 Sk_model::find()和 Sk_model::find_all()方法调用来取得记录。

## 读单个 

获取用户名为Bob的首条活跃用户：

```
$this->load->model ( 'user_model' );
$uid = 1;
$user = $this->user_model->where('uid', $uid)->find();
// 等价于
$user = $this->user_model->find($uid);
// 等价于
$user = $this->user_model->find_by('uid', $uid);
// 等价于
$user = $this->user_model->find_by(array('uid' => $uid));
```

## 读多个 

获取所有叫Bob的用户（多条记录）：

```
$this->load->model ( 'user_model' );
$users = $this->user_model->where('uid >', 1)->find_all();
// 等价于
$users = $this->user_model->find_all_by('uid >', 1);
// 等价于
$users = $this->user_model->find_all_by(array('uid >' => 1));
```

当你使用 Sk_model::find_all 检索了模型列表，你可以遍历它们作为你的数据库结果来使用：

```
foreach ($users as $user) {
  ...
}
```

## 查询生成器(query builder) 

Sk_model 代理调用了　CI_DB_active_record　的方法

注意，如果你还不明白CI_DB_active_record库的查询生成器，你可以参考[[http://codeigniter.org.cn/user_guide/database/active_record.html|CI的Active Record 类]]

```
$this->load->model ( 'user_model' );
$users = $this->user_model
              ->where('sex', 1)     // where sex = 1
              ->where('id >', 1)      // where id > 1
              ->like('name', 'shige') // where name like '%shige%'
              ->find();
```

## 统计行数 

```
$this->load->model ( 'user_model' );
// 统计全部
$n = $this->user_model->count_all();

// 统计男性
$n = $this->user_model->count_all_by('sex', 1);
// 等价于
$n = $this->user_model->count_all_by(array('sex' => 1));
// 等价于
$n = $this->user_model->where('sex', 1)->count_all();
```

# 写 

## 插入 

```
$this->load->model ( 'user_model' );
$user = array('uname' => 'Joe');
$result = $this->user_model->insert($user);
if ($result) {
    ... insert success
} else {
    ... insert fail
    $error = $this->user_model->error;
}
```

## 更新 

```
$this->load->model ( 'user_model' );
$uid = 1;
$user = $this->user_model->find($uid);
$user['uname'] = 'Joe';
$result = $this->user_model->update($uid, $user);
// 等价于
$result = $this->user_model->update(array('uid' => $uid), $user);
// 等价于
$result = $this->user_model->where('uid', $uid)->update(array(), $user);
if ($result) {
    ... save success
} else {
    ... save fail
    $error = $this->user_model->error;
}
```

# 删除 

## 删除记录 

使用 Sk_model::delete() 来删除记录。

```
$this->load->model ( 'user_model' );
$id = 1;
$result = $this->user_model->delete($id);
// 等价于
$result = $this->user_model->delete(array('uid' => $uid));
// 等价于
$result = $this->user_model->where('uid', $uid)->delete();
if ($result) {
    ... delete success
} else {
    ... delete fail
    $error = $this->user_model->error;
}
```

# 模型验证(Validation) 

如果你还不了解CI的校验器CI_Form_validation请参考[[http://codeigniter.org.cn/user_guide/libraries/form_validation.html|表单验证详解]]

你可以使用 $_validation_rules 来列出规则与过滤器，他是一个数组，格式参考[[http://codeigniter.org.cn/user_guide/libraries/form_validation.html#validationrulesasarray|表单验证-使用数组设置验证规则]]

**TODO:** 考虑添加xss_clean的过滤器

```
class User_model extends Sk_model
{
    ... 
    
    /** @var array Validation rules. */
    protected $validation_rules = array(
    array(
        'field' => 'id',
        'label' => '身份证',
        'rules' => 'required|trim|max_length[11]',
    ), 
    array(
        'field' => 'name',
        'label' => '姓名',
        'rules' => 'required|trim|max_length[255]',
    ),
    array(
        'field' => 'birthday',
        'label' => '生日',
        'rules' => 'required|trim|max_length[10]|is_numeric',
    ),
    );
}
```

用法：

```
$this->load->model ( 'user_model' );
$uid = 1;
$user = $this->user_model->find($uid);
$user['uname'] = 'Joe';
$result = $this->user_model->update($uid, $user);
if ($result) {
    ... save success
} else {
    ... save fail
    $error = $this->user_model->error ? $this->user_model->error : validation_errors();
}
```

其中 $this->user_model->error 是保存的错误，validation_errors() 是校验的错误

# 关联关系 

我们支持了3种对象关联关系：
```
belongs_to
has_many
has_one
```

## belongs_to 

用于一对一/多对一。

当一个模型从属于另外的模型时，你可以使用关联关系 belongs_to。

如一个文章从属于一个用户（作者）：Post 模型从属于 User 模型。

```
Post_model extends Sk_model
{
    protected $_belongs_to = array(
        'author' => array(                // 别名：用于访问关联模型
            'model'       => 'User_model',// 模型：从属于哪个模型
            'foreign_key' => 'author_id', // 外键
        ),
    );
}
```

## has_many 

用于一对多。

关联关系 has_many 其实就是关联关系 belongs_to 的反面，表示某个模型拥有多个另外的模型。

在上面的例子，一个文章从属于一个用户。而从用户角度，一个用户拥有多个文章：User 模型拥有多个 Post 模型。

```
User_model extends Sk_model
{
    protected $_has_many = array(
        'posts' => array(                  // 别名，用于访问关联模型
            'model'       => 'Post_model', // 模型：拥有哪个模型
            'foreign_key' => 'author_id',  // 外键
            'order' => 'dateline desc' // 排序，仅has_many的一对多的关系才有排序，一对一不需要排序
        ),
    );
}
```

## has_one 

关联关系 has_one 类似于关联关系 has_many，但它只能是一对一。

如果一个用户只能有一个文章。

```
User_model extends Sk_model
{
    protected $_has_one = array(
        'post' => array(                  // 别名，用于访问关联模型
            'model'       => 'Post_model', // 模型：拥有哪个模型
            'foreign_key' => 'author_id',  // 外键
        ),
    );
}
```

## 关联查询 

**通过 with() 关联查询**

```
$this->load->model ( 'user_model' );
$uid = 1;
$user = $this->user_model->with('post')->find(); // 联查sql：select * from user, post where user.id = $uid and post.author_id = user.id

// 直接通过关联关系的别名作为属性, 来获得关联的文章
$post = $user['post'];
```

**with()不能适用的场景**

with()不能用在两个关联的model的表属于不同数据库的场景，如上所见，不能生成跨库的联查sql

## 只查关联对象 

只查关联表

```
$this->load->model ( 'user_model' );
$uid = 1;
// 查一个
$post = $this->user_model->find_related($uid, 'post'); // 联查sql：select * from post where author_id = $uid

// 查多个
$posts = $this->user_model->find_all_related($uid, 'post'); // 联查sql：select * from post where author_id = $uid

// 查行数
$post_count = $this->user_model->count_related($uid, 'post'); // 联查sql：select count(1) from post where author_id = $uid
```

