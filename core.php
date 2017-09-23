<?php 
namespace {
define('DS', DIRECTORY_SEPARATOR);
define('LIB_PATH', ROOT_PATH . DS . 'lib');
define('CORE_PATH', ROOT_PATH . DS . 'lib/_lib');
if (!defined('VPATH')) {
define('VPATH', getenv('VENDOR_PATH') ? getenv('VENDOR_PATH') : ROOT_PATH);
}
require_once VPATH . DS . '/vendor/autoload.php';
use Symfony\Component\ClassLoader\ClassLoader;
$a = new ClassLoader();
$a->register();
$a->addPrefix('', array(LIB_PATH, CORE_PATH));
}
namespace {
class YamlUserLoader extends \Symfony\Component\Config\Loader\FileLoader
{
public function load($b, $c = null)
{
$d = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($b));
return $d;
}
public function supports($b, $c = null)
{
return is_string($b) && 'yml' === pathinfo($b, PATHINFO_EXTENSION);
}
}
class cfg
{
private static $_yamlFiles = array();
private static $_yamlDatas = array();
private static $_locator = null;
public static function load($e = 'config.yml')
{
if (endWith($e, '.yml')) {
$e = substr($e, 0, -4);
}
if (!isset(self::$_yamlFiles[$e])) {
self::tryLoad($e);
}
if (!isset(self::$_yamlDatas[$e])) {
$f = self::getLocator();
$g = new \Symfony\Component\Config\Loader\LoaderResolver(array(new YamlUserLoader($f)));
$h = new \Symfony\Component\Config\Loader\DelegatingLoader($g);
if (isset(self::$_yamlFiles[$e])) {
$i = self::$_yamlFiles[$e];
self::$_yamlDatas[$e] = $h->load($i);
} else {
debug("cfg: {$e} load fail!");
return [];
}
}
return self::$_yamlDatas[$e];
}
public static function tryLoad($e)
{
$j = ['dev', 'prod', ''];
foreach ($j as $k => $l) {
$m = $e;
if ($l) {
$m .= ".{$l}";
}
$i = self::loadCfg($m . '.yml');
if ($i) {
self::$_yamlFiles[$e] = $i;
return;
}
}
}
public static function loadCfg($e)
{
try {
$n = self::getLocator()->locate($e, null, false);
if ($n) {
return $n[0];
}
} catch (Exception $e) {
}
return null;
}
public static function getLocator()
{
if (!self::$_locator) {
$o = array(ROOT_PATH . '/cfg');
self::$_locator = new \Symfony\Component\Config\FileLocator($o);
}
return self::$_locator;
}
public static function get($k = 'name', $e = 'config.yml')
{
$m = cfg::load($e);
if ($m && isset($m[$k])) {
return $m[$k];
} else {
return null;
}
}
public static function get_db_cfg($k = 'use_db')
{
$p = self::get($k, 'db.yml');
return self::get($p, 'db.yml');
}
public static function get_rest_prefix()
{
$q = \cfg::get('rest_prefix');
return $q ? $q : '/rest';
}
public static function get_redis_cfg()
{
$r = 'redis';
if (is_docker_env()) {
$r = 'docker_redis';
}
return self::get($r, 'db.yml');
}
public static function rest($k)
{
return self::get($k, 'rest');
}
}
class ctx
{
private static $_app = null;
private static $_user = null;
private static $_data = null;
private static $_page = null;
private static $_user_tbl = null;
private static $_uripath = null;
private static $_foundRoute = null;
private static $_rest_prefix = null;
private static $_rest_select_add = '';
private static $_rest_join_add = '';
private static $_rest_extra_data = null;
private static $_global_view_data = null;
private static $_gets = array();
public static function init($s)
{
debug(" -- ctx init -- ");
self::$_user_tbl = \cfg::get('user_tbl_name');
self::$_user = self::getTokenUser(self::$_user_tbl, $s);
self::$_rest_prefix = \cfg::get_rest_prefix();
self::$_uripath = uripath($s);
$t = self::getReqData();
if (!self::isAdminRest() && self::$_user) {
$t['uid'] = self::$_user['id'];
}
$t['_uptm'] = date('Y-m-d H:i:s');
$t['uniqid'] = uniqid();
self::$_data = $t;
$u = get('page', 1);
$v = $u <= 1 ? 1 : $u - 1;
$w = $u + 1;
$x = get('size', 10);
$y = ($u - 1) * $x;
$u = ['page' => $u, 'prepage' => $v, 'nextpage' => $w, 'pagesize' => $x, 'offset' => $y, 'list' => [], 'totalpage' => 0, 'count' => 0, 'isFirstPage' => false, 'isLastPage' => false];
self::$_page = $u;
}
public static function app($app = null)
{
if ($app) {
self::$_app = $app;
}
return self::$_app;
}
public static function container()
{
return self::app()->getContainer();
}
public static function req()
{
return self::container()->request;
}
public static function router()
{
return self::container()->router;
}
public static function route_list()
{
$z = ctx::router()->getRoutes();
$ab = [];
foreach ($z as $k => $ac) {
$ab[] = ['methods' => $ac->getMethods(), 'name' => $ac->getname(), 'pattern' => $ac->getPattern(), 'groups' => $ac->getgroups(), 'arguments' => $ac->getarguments()];
}
return $ab;
}
public static function logger()
{
return self::container()['logger'];
}
public static function user($ad = null)
{
if ($ad) {
self::$_user = $ad;
}
return self::$_user;
}
public static function uid()
{
if (self::user()) {
return self::user()['id'];
} else {
return 0;
}
}
public static function roles()
{
return self::user()['roles'];
}
public static function isRest()
{
return startWith(self::$_uripath, self::$_rest_prefix);
}
public static function isAdmin()
{
return in_array('admin', self::roles());
}
public static function isAdminRest()
{
return startWith(self::$_uripath, self::$_rest_prefix . '_admin');
}
public static function retType()
{
$c = '';
$ae = self::getReqData();
if (isset($ae['ret-type'])) {
$c = $ae['ret-type'];
}
return $c;
}
public static function isRetJson()
{
return self::retType() == 'json';
}
public static function appMode()
{
$m = \cfg::get('app');
$af = isset($m['mode']) ? $m['mode'] : 'normal';
info("app_mode {$af}");
return $af;
}
public static function isStateless()
{
return self::appMode() == 'stateless';
}
public static function isEnableSso()
{
$m = \cfg::get('sso');
return getArg($m, 'enable');
}
public static function uri()
{
return self::$_uripath;
}
public static function data($t = null)
{
if ($t) {
self::$_data = $t;
}
return self::$_data;
}
public static function pageinfo()
{
return self::$_page;
}
public static function page()
{
return self::$_page['page'];
}
public static function pagesize($ag = null)
{
if ($ag) {
self::$_page['pagesize'] = $ag;
}
return self::$_page['pagesize'];
}
public static function offset()
{
return self::$_page['offset'];
}
public static function count($ah)
{
self::$_page['count'] = $ah;
$ai = $ah / self::$_page['pagesize'];
$ai = ceil($ai);
self::$_page['totalpage'] = $ai;
if (self::$_page['page'] == '1') {
self::$_page['isFirstPage'] = true;
}
if (!$ai || self::$_page['page'] == $ai) {
self::$_page['isLastPage'] = true;
}
if (self::$_page['nextpage'] > $ai) {
self::$_page['nextpage'] = $ai ? $ai : 1;
}
$aj = self::$_page['page'] - 4;
$ak = self::$_page['page'] + 4;
if ($ak > $ai) {
$ak = $ai;
$aj = $aj - ($ak - $ai);
}
if ($aj <= 1) {
$aj = 1;
}
if ($ak - $aj < 8 && $ak < $ai) {
$ak = $aj + 8;
}
$ak = $ak ? $ak : 1;
$ab = range($aj, $ak);
self::$_page['list'] = $ab;
return self::$_page['count'];
}
public static function limit()
{
return [self::offset(), self::pagesize()];
}
public static function getReqData()
{
$s = req();
return $s->getParams();
$al = isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : '';
$al = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : $al;
$t = '';
if (!empty($_POST)) {
$t = $_POST;
} else {
$am = file_get_contents("php://input");
if ($am) {
if (strpos($al, 'application/x-www-form-urlencoded') !== false) {
parse_str($am, $an);
$t = $an;
} else {
if (strpos($al, 'application/json') !== false) {
$t = json_decode($am, true);
}
}
}
}
return $t;
}
public static function getToken($s, $k = 'token')
{
$ao = $s->getParam($k);
$t = $s->getParams();
if (!$ao) {
$ao = getArg($t, $k);
}
if (!$ao) {
$ao = getArg($_COOKIE, $k);
}
return $ao;
}
public static function getUcTokenUser($ao)
{
if (!$ao) {
return null;
}
$ap = cache_user($ao);
$aq = $ap['userinfo'] ? $ap['userinfo'] : null;
$aq['id'] = $aq['uid'] = $aq['user_id'];
return $aq;
}
public static function getTokenUser($ar, $s)
{
$as = $s->getParam('uid');
$ad = null;
$at = $s->getParams();
$au = self::check_appid($at);
if ($au && check_sign($at, $au)) {
debug("appkey: {$au}");
$ad = ['id' => $as, 'role' => 'admin'];
} else {
if (self::isStateless()) {
debug("isStateless");
$ad = ['id' => $as, 'role' => 'user'];
} else {
$ao = self::getToken($s);
$av = \cfg::get('use_ucenter_oauth');
if ($av) {
return self::getUcTokenUser($ao);
}
$aw = self::getToken($s, 'access_token');
if (self::isEnableSso()) {
debug("getTokenUserBySso");
$ad = self::getTokenUserBySso($ao);
} else {
debug("get from db");
if ($ao) {
$ad = \db::row($ar, ['token' => $ao]);
} else {
if ($aw) {
$ad = self::getAccessTokenUser($ar, $aw);
}
}
}
}
}
return $ad;
}
public static function check_appid($at)
{
$ax = getArg($at, 'appid');
if ($ax) {
$m = cfg::get('support_service_list', 'service');
if (isset($m[$ax])) {
debug("appid: {$ax} ok");
return $m[$ax];
}
}
debug("appid: {$ax} not ok");
return '';
}
public static function getTokenUserBySso($ao)
{
$ad = ms('sso')->getuserinfo(['token' => $ao])->json();
return $ad;
}
public static function getAccessTokenUser($ar, $aw)
{
$ay = \db::row('oauth_access_tokens', ['access_token' => $aw]);
if ($ay) {
$az = strtotime($ay['expires']);
if ($az - time() > 0) {
$ad = \db::row($ar, ['id' => $ay['user_id']]);
}
}
return $ad;
}
public static function user_tbl($bc = null)
{
if ($bc) {
self::$_user_tbl = $bc;
}
return self::$_user_tbl;
}
public static function render($bd, $be, $bf, $t)
{
$bg = new \Slim\Views\Twig($be, ['cache' => false]);
self::$_foundRoute = true;
return $bg->render($bd, $bf, $t);
}
public static function isFoundRoute()
{
return self::$_foundRoute;
}
public static function rest_prefix()
{
return self::$_rest_prefix;
}
public static function parse_rest()
{
$bh = str_replace(self::$_rest_prefix, '', self::uri());
$bi = explode('/', $bh);
$bj = getArg($bi, 1, '');
$bk = getArg($bi, 2, '');
return [$bj, $bk];
}
public static function rest_select_add($bl = '')
{
if ($bl) {
self::$_rest_select_add = $bl;
}
return self::$_rest_select_add;
}
public static function rest_join_add($bl = '')
{
if ($bl) {
self::$_rest_join_add = $bl;
}
return self::$_rest_join_add;
}
public static function rest_extra_data($t = '')
{
if ($t) {
self::$_rest_extra_data = $t;
}
return self::$_rest_extra_data;
}
public static function global_view_data($t = '')
{
if ($t) {
self::$_global_view_data = $t;
}
return self::$_global_view_data;
}
public static function gets($bm = '', $bn = '')
{
if (!$bm) {
return self::$_gets;
}
if (!$bn) {
return self::$_gets[$bm];
}
if ($bn == '_clear') {
$bn = '';
}
self::$_gets[$bm] = $bn;
return self::$_gets;
}
}
use Medoo\Medoo;
if (!function_exists('fixfn')) {
function fixfn($bo)
{
foreach ($bo as $bp) {
if (!function_exists($bp)) {
eval("function {$bp}(){}");
}
}
}
}
if (!class_exists('cfg')) {
class cfg
{
public static function get_db_cfg()
{
return array('database_type' => 'mysql', 'database_name' => 'kphone', 'server' => 'mysql', 'username' => 'root', 'password' => '123456', 'charset' => 'utf8');
}
}
}
$bo = array('debug');
fixfn($bo);
class db
{
private static $_db_list;
private static $_db_default;
private static $_db;
private static $_dbc;
private static $_ins;
private static $tbl_desc = array();
public static function init($m, $bq = true)
{
self::init_db($m, $bq);
}
public static function new_db($m)
{
return new Medoo($m);
}
public static function get_db_cfg($m = 'use_db')
{
if (is_string($m)) {
$m = \cfg::get_db_cfg($m);
}
$m['database_name'] = env('DB_NAME', $m['database_name']);
$m['server'] = env('DB_HOST', $m['server']);
$m['username'] = env('DB_USER', $m['username']);
$m['password'] = env('DB_PASS', $m['password']);
return $m;
}
public static function init_db($m, $bq = true)
{
self::$_dbc = self::get_db_cfg($m);
$br = self::$_dbc['database_name'];
self::$_db_list[$br] = self::new_db(self::$_dbc);
if ($bq) {
self::use_db($br);
}
}
public static function use_db($br)
{
self::$_db = self::$_db_list[$br];
}
public static function use_default_db()
{
self::$_db = self::$_db_default;
}
public static function dbc()
{
return self::$_dbc;
}
public static function obj()
{
if (!self::$_db) {
self::$_dbc = self::get_db_cfg();
self::$_db = self::$_db_default = self::new_db(self::$_dbc);
}
return self::$_db;
}
public static function db_type()
{
if (!self::$_dbc) {
self::obj();
}
return self::$_dbc['database_type'];
}
public static function desc_sql($bs)
{
if (self::db_type() == 'mysql') {
return "desc {$bs}";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$bs}'";
} else {
return '';
}
}
}
public static function table_cols($bj)
{
$bt = self::$tbl_desc;
if (!isset($bt[$bj])) {
$bu = self::desc_sql($bj);
if ($bu) {
$bt[$bj] = self::query($bu);
self::$tbl_desc = $bt;
debug("---------------- cache not found : {$bj}");
} else {
debug("empty desc_sql for: {$bj}");
}
}
if (!isset($bt[$bj])) {
return array();
} else {
return self::$tbl_desc[$bj];
}
}
public static function col_array($bj)
{
$bv = function ($bn) use($bj) {
return $bj . '.' . $bn;
};
return getKeyValues(self::table_cols($bj), 'Field', $bv);
}
public static function valid_table_col($bj, $bw)
{
$bx = self::table_cols($bj);
foreach ($bx as $by) {
if ($by['Field'] == $bw) {
$c = $by['Type'];
return is_string_column($by['Type']);
}
}
return false;
}
public static function tbl_data($bj, $t)
{
$bx = self::table_cols($bj);
$bz = [];
foreach ($bx as $by) {
$cd = $by['Field'];
if (isset($t[$cd])) {
$bz[$cd] = $t[$cd];
}
}
return $bz;
}
public static function test()
{
$bu = "select * from tags limit 10";
$ce = self::obj()->query($bu)->fetchAll(PDO::FETCH_ASSOC);
var_dump($ce);
}
public static function has_st($bj, $cf)
{
$cg = '_st';
return isset($cf[$cg]) || isset($cf[$bj . '.' . $cg]);
}
public static function getWhere($bj, $ch)
{
$cg = '_st';
if (!self::valid_table_col($bj, $cg)) {
return $ch;
}
$cg = $bj . '._st';
if (is_array($ch)) {
$ci = array_keys($ch);
$cj = preg_grep("/^AND\\s*#?\$/i", $ci);
$ck = preg_grep("/^OR\\s*#?\$/i", $ci);
$cl = array_diff_key($ch, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
if ($cl != array()) {
$cf = $cl;
if (!self::has_st($bj, $cf)) {
$ch[$cg] = 1;
$ch = ['AND' => $ch];
}
}
if (!empty($cj)) {
$l = array_values($cj);
$cf = $ch[$l[0]];
if (!self::has_st($bj, $cf)) {
$ch[$l[0]][$cg] = 1;
}
}
if (!empty($ck)) {
$l = array_values($ck);
$cf = $ch[$l[0]];
if (!self::has_st($bj, $cf)) {
$ch[$l[0]][$cg] = 1;
}
}
if (!isset($ch['AND']) && !self::has_st($bj, $cf)) {
$ch['AND'][$cg] = 1;
}
}
return $ch;
}
public static function all_sql($bj, $ch = '', $cm = '*', $cn = null)
{
$co = [];
if ($cn) {
$bu = self::obj()->selectContext($bj, $co, $cn, $cm, $ch);
} else {
$bu = self::obj()->selectContext($bj, $co, $cm, $ch);
}
return $bu;
}
public static function all($bj, $ch = '', $cm = '*', $cn = null)
{
$ch = self::getWhere($bj, $ch);
if ($cn) {
$ce = self::obj()->select($bj, $cn, $cm, $ch);
} else {
$ce = self::obj()->select($bj, $cm, $ch);
}
return $ce;
}
public static function count($bj, $ch = array('_st' => 1))
{
$ch = self::getWhere($bj, $ch);
return self::obj()->count($bj, $ch);
}
public static function row_sql($bj, $ch = '', $cm = '*', $cn = '')
{
return self::row($bj, $ch, $cm, $cn, true);
}
public static function row($bj, $ch = '', $cm = '*', $cn = '', $cp = null)
{
$ch = self::getWhere($bj, $ch);
if (!isset($ch['LIMIT'])) {
$ch['LIMIT'] = 1;
}
if ($cn) {
if ($cp) {
return self::obj()->selectContext($bj, $cn, $cm, $ch);
}
$ce = self::obj()->select($bj, $cn, $cm, $ch);
} else {
if ($cp) {
return self::obj()->selectContext($bj, $cm, $ch);
}
$ce = self::obj()->select($bj, $cm, $ch);
}
if ($ce) {
return $ce[0];
} else {
return null;
}
}
public static function one($bj, $ch = '', $cm = '*', $cn = '')
{
$cq = self::row($bj, $ch, $cm, $cn);
$cr = '';
if ($cq) {
$cs = array_keys($cq);
$cr = $cq[$cs[0]];
}
return $cr;
}
public static function save($bj, $t, $ct = 'id')
{
$cu = false;
if (!isset($t[$ct])) {
$cu = true;
} else {
if (!self::obj()->has($bj, [$ct => $t[$ct]])) {
$cu = true;
}
}
if ($cu) {
debug("insert {$bj} : " . json_encode($t));
self::obj()->insert($bj, $t);
$t['id'] = self::obj()->id();
} else {
debug("update {$bj} {$ct} {$t[$ct]}");
self::obj()->update($bj, $t, [$ct => $t[$ct]]);
}
return $t;
}
public static function update($bj, $t, $ch)
{
self::obj()->update($bj, $t, $ch);
}
public static function exec($bu)
{
return self::obj()->query($bu);
}
public static function query($bu)
{
info($bu);
return self::obj()->query($bu)->fetchAll(PDO::FETCH_ASSOC);
}
public static function queryRow($bu)
{
$ce = self::query($bu);
if ($ce) {
return $ce[0];
} else {
return null;
}
}
public static function queryOne($bu)
{
$cq = self::queryRow($bu);
return self::oneVal($cq);
}
public static function oneVal($cq)
{
$cr = '';
if ($cq) {
$cs = array_keys($cq);
$cr = $cq[$cs[0]];
}
return $cr;
}
public static function updateBatch($bj, $t)
{
$cv = $bj;
if (!is_array($t) || empty($cv)) {
return FALSE;
}
$bu = "UPDATE `{$cv}` SET";
foreach ($t as $bk => $cq) {
foreach ($cq as $k => $cw) {
$cx[$k][] = "WHEN {$bk} THEN {$cw}";
}
}
foreach ($cx as $k => $cw) {
$bu .= ' `' . trim($k, '`') . '`=CASE id ' . join(' ', $cw) . ' END,';
}
$bu = trim($bu, ',');
$bu .= ' WHERE id IN(' . join(',', array_keys($t)) . ')';
return self::query($bu);
}
}
class fcache
{
const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
const CLEAR_ALL_KEY = 'CLEAR_ALL';
static $_instance = null;
protected $_options = array('cache_dir' => './cache', 'file_locking' => true, 'file_name_prefix' => 'cache', 'cache_file_umask' => 0777, 'file_life' => 100000);
static function &getInstance($cy = array())
{
if (self::$_instance === null) {
self::$_instance = new self($cy);
}
return self::$_instance;
}
static function &setOptions($cy = array())
{
return self::getInstance($cy);
}
private function __construct($cy = array())
{
if ($this->_options['cache_dir'] !== null) {
$be = rtrim($this->_options['cache_dir'], '/') . '/';
$this->_options['cache_dir'] = $be;
if (!is_dir($this->_options['cache_dir'])) {
mkdir($this->_options['cache_dir'], 0777, TRUE);
}
if (!is_writable($this->_options['cache_dir'])) {
exit('file_cache: 路径 "' . $this->_options['cache_dir'] . '" 不可写');
}
} else {
exit('file_cache: "options" cache_dir 不能为空 ');
}
}
static function setCacheDir($l)
{
$cz =& self::getInstance();
if (!is_dir($l)) {
exit('file_cache: ' . $l . ' 不是一个有效路径 ');
}
if (!is_writable($l)) {
exit('file_cache: 路径 "' . $l . '" 不可写');
}
$l = rtrim($this->_options['cache_dir'], '/') . '/';
$cz->_options['cache_dir'] = $l;
}
static function save($t, $bk = null, $de = null)
{
$cz =& self::getInstance();
if (!$bk) {
if ($cz->_id) {
$bk = $cz->_id;
} else {
exit('file_cache:save() id 不能为空!');
}
}
$df = time();
if ($de) {
$t[self::FILE_LIFE_KEY] = $df + $de;
} elseif ($de != 0) {
$t[self::FILE_LIFE_KEY] = $df + $cz->_options['file_life'];
}
$dg = $cz->_file($bk);
$t = "\n" . " // mktime: " . $df . "\n" . " return " . var_export($t, true) . "\n?>";
$dh = $cz->_filePutContents($dg, $t);
return $dh;
}
static function load($bk)
{
$cz =& self::getInstance();
$df = time();
if (!$cz->test($bk)) {
return false;
}
$di = $cz->_file(self::CLEAR_ALL_KEY);
$dg = $cz->_file($bk);
if (is_file($di) && filemtime($di) > filemtime($dg)) {
return false;
}
$t = $cz->_fileGetContents($dg);
if (empty($t[self::FILE_LIFE_KEY]) || $df < $t[self::FILE_LIFE_KEY]) {
unset($t[self::FILE_LIFE_KEY]);
return $t;
}
return false;
}
protected function _filePutContents($dg, $dj)
{
$cz =& self::getInstance();
$dk = false;
$dl = @fopen($dg, 'ab+');
if ($dl) {
if ($cz->_options['file_locking']) {
@flock($dl, LOCK_EX);
}
fseek($dl, 0);
ftruncate($dl, 0);
$dm = @fwrite($dl, $dj);
if (!($dm === false)) {
$dk = true;
}
@fclose($dl);
}
@chmod($dg, $cz->_options['cache_file_umask']);
return $dk;
}
protected function _file($bk)
{
$cz =& self::getInstance();
$dn = $cz->_idToFileName($bk);
return $cz->_options['cache_dir'] . $dn;
}
protected function _idToFileName($bk)
{
$cz =& self::getInstance();
$cz->_id = $bk;
$q = $cz->_options['file_name_prefix'];
$dk = $q . '---' . $bk;
return $dk;
}
static function test($bk)
{
$cz =& self::getInstance();
$dg = $cz->_file($bk);
if (!is_file($dg)) {
return false;
}
return true;
}
protected function _fileGetContents($dg)
{
if (!is_file($dg)) {
return false;
}
return include $dg;
}
static function clear()
{
$cz =& self::getInstance();
$cz->save('CLEAR_ALL', self::CLEAR_ALL_KEY);
}
static function del($bk)
{
$cz =& self::getInstance();
if (!$cz->test($bk)) {
return false;
}
$dg = $cz->_file($bk);
return unlink($dg);
}
}
class mdb
{
private static $_client;
private static $_db;
private static $_ins;
public static function obj($br = 'myapp_dev')
{
if (!self::$_client) {
self::$_client = new \Sokil\Mongo\Client();
}
if (!self::$_db) {
self::$_db = self::$_client->{$br};
}
return self::$_db;
}
public static function test()
{
$as = 1;
$do = self::obj()->blogs;
$dp = $do->find()->findAll();
$t = object2array($dp);
$dq = 1;
foreach ($t as $bm => $dr) {
unset($dr['_id']);
unset($dr['tid']);
unset($dr['tags']);
if (isset($dr['_intm'])) {
$dr['_intm'] = date('Y-m-d H:i:s', $dr['_intm']['sec']);
}
if (isset($dr['_uptm'])) {
$dr['_uptm'] = date('Y-m-d H:i:s', $dr['_uptm']['sec']);
}
$dr['uid'] = $as;
$bz = db::save('blogs', $dr);
$dq++;
}
echo 'finish test';
die;
}
}
class rds
{
private static $_client;
private static $_ins;
private static $tbl_desc = array();
public static function obj()
{
if (!self::$_client) {
self::$_client = $ds = new Predis\Client(cfg::get_redis_cfg());
}
return self::$_client;
}
}
class uc
{
static $UC_HOST = 'http://uc.xxx.com/';
const API = array('user' => '/api/user', 'accessToken' => '/api/oauth/accessToken', 'userRole' => '/api/user/role', 'createDomain' => "/api/domain", 'finduser' => '/api/users', 'userdomain' => '/api/user/domain');
static $code_user = null;
static $pwd_user = null;
static $user_info = null;
static $user_role = null;
private static $oauth_cfg;
public static function init($dt = array())
{
$m = cfg::get('oauth', 'oauth')['uc'];
$m['host'] = env('UC_HOST', $m['host']);
$m['clientId'] = env('UC_CLIENT_ID', $m['clientId']);
$m['clientSecret'] = env('UC_CLIENT_SECRET', $m['clientSecret']);
$m['redirectUri'] = env('UC_REDIRECT_URI', $m['redirectUri']);
$m['username'] = env('UC_USERNAME', $m['username']);
$m['passwd'] = env('UC_PASSWD', $m['passwd']);
self::$oauth_cfg = $m;
if (isset($dt['host'])) {
self::$UC_HOST = $dt['host'];
}
}
public static function makeUrl($bh, $at = '')
{
return self::$oauth_cfg['host'] . $bh . ($at ? '?' . $at : '');
}
public static function pwd_login($du = null, $dv = null, $dw = null, $dx = null)
{
extract(self::$oauth_cfg);
if ($du) {
$dy = $du;
}
if ($dv) {
$dz = $dv;
}
if ($dw) {
$ef = $dw;
}
if ($dx) {
$eg = $dx;
}
$t = ['client_id' => $ef, 'client_secret' => $eg, 'grant_type' => 'password', 'username' => $dy, 'password' => $dz];
$eh = self::makeUrl(self::API['accessToken']);
$ei = curl($eh, 10, 30, $t);
$bz = json_decode($ei, true);
self::_set_pwd_user($bz);
return $bz;
}
public static function authurl($ax, $ej, $aw)
{
$ek = self::$oauth_cfg['host'] . "/api/sso/redirect?access_token={$aw}&app_id={$ax}&domain_id={$ej}";
return $ek;
}
public static function code_login($el, $em = null, $dw = null, $dx = null)
{
extract(self::$oauth_cfg);
if ($em) {
$en = $em;
}
if ($dw) {
$ef = $dw;
}
if ($dx) {
$eg = $dx;
}
$t = ['client_id' => $ef, 'client_secret' => $eg, 'grant_type' => 'authorization_code', 'redirect_uri' => $en, 'code' => $el];
$eh = self::makeUrl(self::API['accessToken']);
$ei = curl($eh, 10, 30, $t);
$bz = json_decode($ei, true);
self::_set_code_user($bz);
return $bz;
}
public static function user_info($aw)
{
$eh = self::makeUrl(self::API['user'], 'access_token=' . $aw);
$ei = curl($eh);
$bz = json_decode($ei, true);
self::_set_user_info($bz);
return $bz;
}
public static function reg_user($aw, $eo, $dv = '123456')
{
$t = ['phone' => $eo, 'password' => $dv, 'access_token' => $aw];
$eh = self::makeUrl(self::API['user']);
$ei = curl($eh, 10, 30, $t);
$ep = json_decode($ei, true);
return $ep;
}
public static function register_user($eo, $dv = '123456')
{
extract(self::$oauth_cfg);
$bz = uc::pwd_login($dy, $dz, $ef, $eg);
$aw = $bz['data']['access_token'];
return self::reg_user($aw, $eo, $dv);
}
public static function find_user($aw, $eq = array())
{
$at = 'access_token=' . $aw;
if (isset($eq['username'])) {
$at .= '&username=' . $eq['username'];
}
if (isset($eq['phone'])) {
$at .= '&phone=' . $eq['phone'];
}
$eh = self::makeUrl(self::API['finduser'], $at);
$ei = curl($eh, 10, 30);
$ep = json_decode($ei, true);
return $ep;
}
public static function set_user_role($aw, $ej, $er, $es = 'guest')
{
$t = ['access_token' => $aw, 'domain_id' => $ej, 'user_id' => $er, 'role_name' => $es];
$eh = self::makeUrl(self::API['userRole']);
$ei = curl($eh, 10, 30, $t);
return json_decode($ei, true);
}
public static function user_role($aw, $ej)
{
$t = ['access_token' => $aw, 'domain_id' => $ej];
$eh = self::makeUrl(self::API['userRole']);
$eh = "{$eh}?access_token={$aw}&domain_id={$ej}";
$ei = curl($eh, 10, 30);
$bz = json_decode($ei, true);
self::_set_user_role($bz);
return $bz;
}
public static function has_role($et)
{
if (self::$user_role && isset(self::$user_role['roles'])) {
$eu = self::$user_role['roles'];
foreach ($eu as $k => $es) {
if ($es['name'] == $et) {
return true;
}
}
}
return false;
}
public static function _set_pwd_user($bz)
{
if ($bz['code'] == 0) {
self::$pwd_user = $bz['data'];
}
}
public static function _set_code_user($bz)
{
if ($bz['code'] == 0) {
self::$code_user = $bz['data'];
}
}
public static function _set_user_info($bz)
{
if ($bz['code'] == 0) {
self::$user_info = $bz['data'];
}
}
public static function _set_user_role($bz)
{
if ($bz['code'] == 0) {
self::$user_role = $bz['data'];
}
}
}
class vld
{
public static function test($bj, $t)
{
}
public static function registration($t)
{
$bn = new Valitron\Validator($t);
$ev = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$bn->rules($ev);
$bn->labels(['name' => '名称', 'gender' => '性别', 'birthdate' => '生日']);
if ($bn->validate()) {
return 0;
} else {
err($bn->errors());
}
}
}
}
namespace mid {
use FastRoute\Dispatcher;
use FastRoute\RouteParser\Std as StdParser;
class TwigMid
{
public function __invoke($ew, $ex, $ey)
{
log_time("Twig Begin");
$ex = $ey($ew, $ex);
$ez = uripath($ew);
debug(">>>>>> TwigMid START : {$ez}  <<<<<<");
if ($fg = $this->getRoutePath($ew)) {
$bg = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($bg->data);
}
$fh = rtrim($fg, '/');
if ($fh == '/' || !$fh) {
$fh = 'index';
}
$bf = $fh;
$t = [];
if (isset($bg->data)) {
$t = $bg->data;
if (isset($bg->data['tpl'])) {
$bf = $bg->data['tpl'];
}
}
$t['uid'] = \ctx::uid();
$t['isLogin'] = \ctx::user() ? true : false;
$t['user'] = \ctx::user();
$t['uri'] = \ctx::uri();
$t['t'] = time();
$t['domain'] = \cfg::get('wechat_callback_domain');
$t['gdata'] = \ctx::global_view_data();
debug("<<<<<< TwigMid END : {$ez} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $bg->render($ex, tpl($bf), $t);
} else {
return $ex;
}
}
public function getRoutePath($ew)
{
$fi = \ctx::router()->dispatch($ew);
if ($fi[0] === Dispatcher::FOUND) {
$ac = \ctx::router()->lookupRoute($fi[1]);
$fj = $ac->getPattern();
$fk = new StdParser();
$fl = $fk->parse($fj);
foreach ($fl as $fm) {
foreach ($fm as $fn) {
if (is_string($fn)) {
return $fn;
}
}
}
}
return '';
}
}
}
namespace mid {
class AuthMid
{
private $isAjax = false;
public function __invoke($ew, $ex, $ey)
{
log_time("AuthMid Begin");
$ez = uripath($ew);
debug(">>>>>> AuthMid START : {$ez}  <<<<<<");
\ctx::init($ew);
$this->check_auth($ew, $ex);
debug("<<<<<< AuthMid END : {$ez} >>>>>");
log_time("AuthMid END");
$ex = $ey($ew, $ex);
return $ex;
}
public function isAjax($bh = '')
{
if ($bh) {
if (startWith($bh, '/rest')) {
$this->isAjax = true;
}
}
return $this->isAjax;
}
public function check_auth($s, $bd)
{
list($fo, $ad, $fp) = $this->auth_cfg();
$ez = uripath($s);
$this->isAjax($ez);
if ($ez == '/') {
return true;
}
$fq = $this->check_list($fo, $ez);
if ($fq) {
$this->check_admin();
}
$fr = $this->check_list($ad, $ez);
if ($fr) {
$this->check_user();
}
$fs = $this->check_list($fp, $ez);
if (!$fs) {
$this->check_user();
}
info("check_auth: {$ez} admin:[{$fq}] user:[{$fr}] pub:[{$fs}]");
}
public function check_admin()
{
$ad = \ctx::user();
if (isset($ad['role']) && $ad['role'] == 'admin') {
return true;
}
$this->auth_error(2);
}
public function check_user()
{
if (!\ctx::user()) {
$this->auth_error();
}
}
public function auth_error($ft = 1)
{
$fu = is_weixin();
$fv = isMobile();
$fw = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$ft}, is_weixin: {$fu} , is_mobile: {$fv}");
$fx = $_SERVER['REQUEST_URI'];
if ($fu) {
header("Location: {$fw}/auth/wechat?_r={$fx}");
exit;
}
if ($fv) {
header("Location: {$fw}/auth/openwechat?_r={$fx}");
exit;
}
if ($this->isAjax()) {
ret($ft, 'auth error');
} else {
header('Location: /?_r=' . $fx);
exit;
}
}
public function auth_cfg()
{
$fy = \cfg::get('auth');
return [$fy['admin'], $fy['user'], $fy['public']];
}
public function check_list($ab, $ez)
{
foreach ($ab as $bh) {
if (startWith($ez, $bh)) {
return true;
}
}
return false;
}
}
}
namespace mid {
class BaseMid
{
use \core\PropGeneratorTrait;
private $name = 'Base';
private $classname = '';
private $path_info = '';
public function __invoke($ew, $ex, $ey)
{
$this->init($ew, $ex, $ey);
log_time("{$this->classname} Begin");
$this->path_info = uripath($ew);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($ew, $ex);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$ex = $ey($ew, $ex);
return $ex;
}
public function handelReq($s, $bd)
{
$bh = \cfg::get($this->classname, 'mid.yml');
if (is_array($bh)) {
$this->handlePathArray($bh, $s, $bd);
} else {
if (startWith($this->path_info, $bh)) {
$this->handlePath($s, $bd);
}
}
}
public function handlePathArray($fz, $s, $bd)
{
foreach ($fz as $bh => $gh) {
if (startWith($this->path_info, $bh)) {
debug("{$this->path_info} match {$bh} {$gh}");
$this->{$gh}($s, $bd);
break;
}
}
}

public function handlePath($s, $bd)
{
debug("handle Path {$this->path_info} .....");
}
}
}
namespace mid {
use db\Rest as rest;
use db\RestDoc as rd;
use db\Tagx as tag;
class RestMid
{
private $path_info;
private $rest_prefix;
public function __invoke($ew, $ex, $ey)
{
log_time("RestMid Begin");
$this->path_info = uripath($ew);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($ew)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($ew)) {
$this->apiDoc($ew);
} else {
$this->handelRest($ew);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$ex = $ey($ew, $ex);
return $ex;
}
public function isApiDoc($s)
{
return startWith($this->path_info, $this->rest_prefix . '/apidoc');
}
public function isRest($s)
{
return startWith($this->path_info, $this->rest_prefix);
}
public function handelRest($s)
{
$bh = str_replace($this->rest_prefix, '', $this->path_info);
$bi = explode('/', $bh);
$bj = getArg($bi, 1, '');
$bk = getArg($bi, 2, '');
$gh = $s->getMethod();
info(" method: {$gh}, name: {$bj}, id: {$bk}");
$gi = "handle{$gh}";
$this->{$gi}($s, $bj, $bk);
}
public function handleGET($s, $bj, $bk)
{
if ($bk) {
rest::renderItem($bj, $bk);
} else {
rest::renderList($bj);
}
}
public function handlePOST($s, $bj, $bk)
{
self::beforeData($bj, 'post');
rest::renderPostData($bj);
}
public function handlePUT($s, $bj, $bk)
{
self::beforeData($bj, 'put');
rest::renderPutData($bj, $bk);
}
public function handleDELETE($s, $bj, $bk)
{
rest::delete($s, $bj, $bk);
}
public function handleOPTIONS($s, $bj, $bk)
{
sendJson([]);
}
public function beforeData($bj, $c)
{
$gj = \cfg::get('rest_maps', 'rest.yml');
$m = $gj[$bj][$c];
if ($m) {
$gk = $m['xmap'];
if ($gk) {
$t = \ctx::data();
foreach ($gk as $bm => $bn) {
unset($t[$bn]);
}
\ctx::data($t);
}
}
}
public function apiDoc($s)
{
$gl = rd::genApi();
echo $gl;
die;
}
}
}
namespace db {
use db\Tagx as tag;
use Symfony\Component\Yaml\Yaml;
class Rest
{
private static $tbl_desc = array();
public static function whereStr($ch, $bj)
{
$bz = '';
foreach ($ch as $bm => $bn) {
$fj = '/(.*)\\{(.*)\\}/i';
$bl = preg_match($fj, $bm, $gm);
$gn = '=';
if ($gm) {
$go = $gm[1];
$gn = $gm[2];
} else {
$go = $bm;
}
if ($gp = \db::valid_table_col($bj, $go)) {
if ($gp == 2) {
$bz .= " and t1.{$go}{$gn}'{$bn}'";
} else {
$bz .= " and t1.{$go}{$gn}{$bn}";
}
} else {
}
info("[{$bj}] [{$go}] [{$gp}] {$bz}");
}
return $bz;
}
public static function getSqlFrom($bj, $gq, $as, $gr, $gs)
{
$gt = isset($_GET['tags']) ? 1 : 0;
$gu = isset($_GET['isar']) ? 1 : 0;
$gv = \cfg::rest('rest_xwh_tags_list');
if ($gv && in_array($bj, $gv)) {
$gt = 0;
}
$gw = $gu ? "1=1" : "t1.uid={$as}";
if ($gt) {
$gx = get('tags');
if ($gx && is_array($gx) && count($gx) == 1 && !$gx[0]) {
$gx = '';
}
$gy = '';
$gz = 'not in';
if ($gx) {
if (is_string($gx)) {
$gx = [$gx];
}
$hi = implode("','", $gx);
$gy = "and `name` in ('{$hi}')";
$gz = 'in';
$hj = " from {$bj} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$gq}\n                               where {$gw} and t._st=1  and t.tagid {$gz}\n                               (select id from tags where type='{$bj}' {$gy} )\n                               {$gs}";
} else {
$hj = " from {$bj} t1\n                              {$gq}\n                              where {$gw} and t1.id not in\n                              (select oid from tag_items where type='{$bj}')\n                              {$gs}";
}
} else {
$hk = $gw;
if (!\ctx::isAdmin()) {
if ($bj == \ctx::user_tbl()) {
$hk = "t1.id={$as}";
}
}
$hj = "from {$bj} t1 {$gq} where {$hk} {$gr} {$gs}";
}
return $hj;
}
public static function getSql($bj)
{
$as = \ctx::uid();
$hl = get('sort', '_intm');
$hm = get('asc', -1);
if (!\db::valid_table_col($bj, $hl)) {
$hl = '_intm';
}
$hm = $hm > 0 ? 'asc' : 'desc';
$gs = " order by t1.{$hl} {$hm}";
$hn = gets();
$hn = un_select_keys(['sort', 'asc'], $hn);
$ho = get('_st', 1);
$ch = dissoc($hn, ['token', '_st']);
if ($ho != 'all') {
$ch['_st'] = $ho;
}
$gr = self::whereStr($ch, $bj);
$hp = get('search', '');
$hq = get('search-key', '');
if ($hp && $hq) {
$gr .= " and {$hq} like '%{$hp}%'";
}
$hr = \ctx::rest_select_add();
$gq = \ctx::rest_join_add();
$hj = self::getSqlFrom($bj, $gq, $as, $gr, $gs);
$bu = "select t1.* {$hr} {$hj}";
$hs = "select count(*) cnt {$hj}";
$y = \ctx::offset();
$x = \ctx::pagesize();
$bu .= " limit {$y},{$x}";
return [$bu, $hs];
}
public static function getResName($bj)
{
$ht = get('res_id_key', '');
if ($ht) {
$hu = get($ht);
$bj .= '_' . $hu;
}
return $bj;
}
public static function getList($bj, $hv = array())
{
$as = \ctx::uid();
list($bu, $hs) = self::getSql($bj);
$ce = \db::query($bu);
$ah = (int) \db::queryOne($hs);
$hw = \cfg::rest('rest_join_tags_list');
if ($hw && in_array($bj, $hw)) {
$hx = getKeyValues($ce, 'id');
$gx = tag::getTagsByOids($as, $hx, $bj);
info("get tags ok: {$as} {$bj} " . json_encode($hx));
foreach ($ce as $bm => $cq) {
if (isset($gx[$cq['id']])) {
$hy = $gx[$cq['id']];
$ce[$bm]['tags'] = getKeyValues($hy, 'name');
}
}
info('set tags ok');
}
if (isset($hv['join_cols'])) {
foreach ($hv['join_cols'] as $hz => $ij) {
$ik = getArg($ij, 'jtype', '1-1');
$il = $ij['jkeys'];
if (is_string($ij['on'])) {
$im = 'id';
$in = $ij['on'];
} else {
if (is_array($ij['on'])) {
$io = array_keys($ij['on']);
$im = $io[0];
$in = $ij['on'][$im];
}
}
info("------jopt: {$ik}  {$im}  {$in} ");
$hx = getKeyValues($ce, $im);
$ip = \db::all($hz, ['AND' => [$in => $hx]]);
foreach ($ip as $k => $iq) {
info($iq);
foreach ($ce as $bm => &$cq) {
if ($cq[$im] == $iq[$in]) {
if ($ik == '1-1') {
foreach ($il as $ir => $is) {
$cq[$is] = $iq[$ir];
}
}
if ($ik == '1-n') {
$ir = $ij['jkey'];
$cq[$ir][] = $iq[$ir];
}
}
}
}
}
}
info('before ret');
$it = self::getResName($bj);
return ['data' => $ce, 'res-name' => $it, 'count' => $ah];
}
public static function renderList($bj)
{
ret(self::getList($bj));
}
public static function getItem($bj, $bk)
{
$as = \ctx::uid();
info("---GET---: {$bj}/{$bk}");
$it = "{$bj}-{$bk}";
if ($bj == 'colls') {
$fn = \db::row($bj, ["{$bj}.id" => $bk], ["{$bj}.id", "{$bj}.title", "{$bj}.from_url", "{$bj}._intm", "{$bj}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($bj == 'feeds') {
$c = get('type');
$iu = get('rid');
$fn = \db::row($bj, ['AND' => ['uid' => $as, 'rid' => $bk, 'type' => $c]]);
if (!$fn) {
$fn = ['rid' => $bk, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$it = "{$it}-{$c}-{$bk}";
} else {
$fn = \db::row($bj, ['id' => $bk]);
}
}
if (\ctx::rest_extra_data()) {
$fn = array_merge($fn, \ctx::rest_extra_data());
}
return ['data' => $fn, 'res-name' => $it, 'count' => 1];
}
public static function renderItem($bj, $bk)
{
ret(self::getItem($bj, $bk));
}
public static function postData($bj)
{
$t = \db::tbl_data($bj, \ctx::data());
$as = \ctx::uid();
$gx = [];
if ($bj == 'tags') {
$gx = tag::getTagByName($as, $t['name'], $t['type']);
}
if ($gx && $bj == 'tags') {
$t = $gx[0];
} else {
info("---POST---: {$bj} " . json_encode($t));
unset($t['token']);
$t['_intm'] = date('Y-m-d H:i:s');
if (!isset($t['uid'])) {
$t['uid'] = $as;
}
$t = \db::tbl_data($bj, $t);
\vld::test($bj, $t);
$t = \db::save($bj, $t);
}
return $t;
}
public static function renderPostData($bj)
{
$t = self::postData($bj);
ret($t);
}
public static function putData($bj, $bk)
{
if ($bk == 0 || $bk == '' || trim($bk) == '') {
info(" PUT ID IS EMPTY !!!");
ret();
}
$as = \ctx::uid();
$t = \ctx::data();
unset($t['token']);
unset($t['uniqid']);
self::checkOwner($bj, $bk, $as);
if (isset($t['inc'])) {
$iv = $t['inc'];
unset($t['inc']);
\db::exec("UPDATE {$bj} SET {$iv} = {$iv} + 1 WHERE id={$bk}");
}
if (isset($t['dec'])) {
$iv = $t['dec'];
unset($t['dec']);
\db::exec("UPDATE {$bj} SET {$iv} = {$iv} - 1 WHERE id={$bk}");
}
if (isset($t['tags'])) {
info("up tags");
tag::delTagByOid($as, $bk, $bj);
$gx = $t['tags'];
foreach ($gx as $iw) {
$ix = tag::getTagByName($as, $iw, $bj);
info($ix);
if ($ix) {
$iy = $ix[0]['id'];
tag::saveTagItems($as, $iy, $bk, $bj);
}
}
}
info("---PUT---: {$bj}/{$bk} " . json_encode($t));
$t = \db::tbl_data($bj, \ctx::data());
$t['id'] = $bk;
\db::save($bj, $t);
return $t;
}
public static function renderPutData($bj, $bk)
{
$t = self::putData($bj, $bk);
ret($t);
}
public static function delete($s, $bj, $bk)
{
$as = \ctx::uid();
self::checkOwner($bj, $bk, $as);
\db::save($bj, ['_st' => 0, 'id' => $bk]);
ret([]);
}
public static function checkOwner($bj, $bk, $as)
{
$ch = ['AND' => ['id' => $bk], 'LIMIT' => 1];
$ce = \db::obj()->select($bj, '*', $ch);
if ($ce) {
$fn = $ce[0];
} else {
$fn = null;
}
if ($fn) {
if (array_key_exists('uid', $fn)) {
$iz = $fn['uid'];
if ($bj == \ctx::user_tbl()) {
$iz = $fn['id'];
}
if ($iz != $as && (!\ctx::isAdmin() || !\ctx::isAdminRest())) {
ret(311, 'owner error');
}
} else {
if (!\ctx::isAdmin()) {
ret(311, 'owner error');
}
}
} else {
ret(312, 'not found error');
}
}
}
}
namespace db {
class Tagx
{
public static $tbl_name = 'tags';
public static $tbl_items_name = 'tag_items';
public static function getTagByName($as, $iw, $c)
{
$gx = \db::all(self::$tbl_name, ['AND' => ['uid' => $as, 'name' => $iw, 'type' => $c, '_st' => 1]]);
return $gx;
}
public static function delTagByOid($as, $jk, $jl)
{
info("del tag: {$as}, {$jk}, {$jl}");
$bz = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $as, 'oid' => $jk, 'type' => $jl]]);
info($bz);
}
public static function saveTagItems($as, $jm, $jk, $jl)
{
\db::save('tag_items', ['tagid' => $jm, 'uid' => $as, 'oid' => $jk, 'type' => $jl, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($as, $c)
{
$gx = \db::all(self::$tbl_name, ['AND' => ['uid' => $as, 'type' => $c, '_st' => 1]]);
return $gx;
}
public static function getTagsByOid($as, $jk, $c)
{
$bu = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$jk} and t2.type='{$c}' and t2._st=1";
$ce = \db::query($bu);
return getKeyValues($ce, 'name');
}
public static function getTagsByOids($as, $jn, $c)
{
if (is_array($jn)) {
$jn = implode(',', $jn);
}
$bu = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$jn}) and t2.type='{$c}' and t2._st=1";
$ce = \db::query($bu);
$t = groupArray($ce, 'oid');
return $t;
}
public static function countByTag($as, $iw, $c)
{
$bu = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$iw}' and t1.type='{$c}' and t1.uid={$as}";
$ce = \db::query($bu);
return [$ce[0]['cnt'], $ce[0]['id']];
}
public static function saveTag($as, $iw, $c)
{
$t = ['uid' => $as, 'name' => $iw, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$t = \db::save('tags', $t);
return $t;
}
public static function countTags($as, $jo, $bj)
{
foreach ($jo as $iw) {
list($jp, $bk) = self::countByTag($as, $iw, $bj);
echo "{$iw} {$jp} {$bk} <br>";
\db::update('tags', ['count' => $jp], ['id' => $bk]);
}
}
public static function saveRepoTags($as, $jq)
{
$bj = 'stars';
echo count($jq) . "<br>";
$jo = [];
foreach ($jq as $jr) {
$js = $jr['repoId'];
$gx = isset($jr['tags']) ? $jr['tags'] : [];
if ($gx) {
foreach ($gx as $iw) {
if (!in_array($iw, $jo)) {
$jo[] = $iw;
}
$gx = self::getTagByName($as, $iw, $bj);
if (!$gx) {
$ix = self::saveTag($as, $iw, $bj);
} else {
$ix = $gx[0];
}
$jm = $ix['id'];
$jt = getStarByRepoId($as, $js);
if ($jt) {
$jk = $jt[0]['id'];
$ju = self::getTagsByOid($as, $jk, $bj);
if ($ix && !in_array($iw, $ju)) {
self::saveTagItems($as, $jm, $jk, $bj);
}
} else {
echo "-------- star for {$js} not found <br>";
}
}
} else {
}
}
self::countTags($as, $jo, $bj);
}
public static function getTagItem($jv, $as, $jw, $ct, $jx)
{
$bu = "select * from {$jw} where {$ct}={$jx} and uid={$as}";
return $jv->query($bu)->fetchAll();
}
public static function saveItemTags($jv, $as, $bj, $jy, $ct = 'id')
{
echo count($jy) . "<br>";
$jo = [];
foreach ($jy as $jz) {
$jx = $jz[$ct];
$gx = isset($jz['tags']) ? $jz['tags'] : [];
if ($gx) {
foreach ($gx as $iw) {
if (!in_array($iw, $jo)) {
$jo[] = $iw;
}
$gx = getTagByName($jv, $as, $iw, $bj);
if (!$gx) {
$ix = saveTag($jv, $as, $iw, $bj);
} else {
$ix = $gx[0];
}
$jm = $ix['id'];
$jt = getTagItem($jv, $as, $bj, $ct, $jx);
if ($jt) {
$jk = $jt[0]['id'];
$ju = getTagsByOid($jv, $as, $jk, $bj);
if ($ix && !in_array($iw, $ju)) {
saveTagItems($jv, $as, $jm, $jk, $bj);
}
} else {
echo "-------- star for {$jx} not found <br>";
}
}
} else {
}
}
countTags($jv, $as, $jo, $bj);
}
}
}
namespace core {
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
class Service
{
public $service = '';
public $prefix = '';
public $client;
public $resp;
private static $_services = array();
private static $_ins = array();
public function __construct($kl = '')
{
if ($kl) {
$this->service = $kl;
$hv = self::$_services[$this->service];
$km = $hv['url'];
debug("init client: {$km}");
$this->client = new Client(['base_uri' => $km, 'timeout' => 12.0]);
}
}
public static function add($hv = array())
{
if ($hv) {
$bj = $hv['name'];
if (!isset(self::$_services[$bj])) {
self::$_services[$bj] = $hv;
}
}
}
public static function init()
{
$kn = \cfg::get('service_list', 'service');
foreach ($kn as $m) {
self::add($m);
}
}
public function getRest($kl, $q = '/rest')
{
return $this->get($kl, $q . '/');
}
public function get($kl, $q = '')
{
if (isset(self::$_services[$kl])) {
if (!isset(self::$_ins[$kl])) {
self::$_ins[$kl] = new Service($kl);
}
}
if (isset(self::$_ins[$kl])) {
$ko = self::$_ins[$kl];
if ($q) {
$ko->setPrefix($q);
}
return $ko;
} else {
return null;
}
}
public function setPrefix($q)
{
$this->prefix = $q;
}
public function __call($kp, $kq)
{
$hv = self::$_services[$this->service];
$km = $hv['url'];
$ax = $hv['appid'];
$au = $hv['appkey'];
$t = $kq[0];
$t = array_merge($t, $_GET);
$t['appid'] = $ax;
$t['date'] = date("Y-m-d H:i:s");
$t['sign'] = gen_sign($t, $au);
$gh = getArg($kq, 1, 'GET');
$kr = getArg($kq, 2, '');
$kp = $this->prefix . $kp . $kr;
debug("api_url: {$ax} {$au} {$km}");
debug("api_name: {$kp} {$gh}");
debug("data: " . json_encode($t));
try {
$this->resp = $this->client->request($gh, $kp, ['form_params' => $t]);
} catch (Exception $e) {
}
return $this;
}
public function json()
{
$bl = $this->body();
$t = json_decode($bl, true);
return $t;
}
public function body()
{
if ($this->resp) {
return $this->resp->getBody();
}
}
}
}
namespace core {
trait PropGeneratorTrait
{
public function __get($ks)
{
$gh = 'get' . ucfirst($ks);
if (method_exists($this, $gh)) {
$kt = new ReflectionMethod($this, $gh);
if (!$kt->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $ks)) {
return $this->{$ks};
}
}
public function __set($ks, $l)
{
$gh = 'set' . ucfirst($ks);
if (method_exists($this, $gh)) {
$kt = new ReflectionMethod($this, $gh);
if (!$kt->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $ks)) {
$this->{$ks} = $l;
}
}
}
}
namespace {
error_reporting(E_ALL);
function cache_shutdown_error()
{
$ku = error_get_last();
if ($ku && in_array($ku['type'], array(1, 4, 16, 64, 256, 4096, E_ALL))) {
echo '<font color=red>你的代码出错了：</font></br>';
echo '致命错误:' . $ku['message'] . '</br>';
echo '文件:' . $ku['file'] . '</br>';
echo '在第' . $ku['line'] . '行</br>';
}
}
register_shutdown_function("cache_shutdown_error");
function getCaller($kv = NULL)
{
$kw = debug_backtrace();
$kx = $kw[2];
if (isset($kv)) {
return $kx[$kv];
} else {
return $kx;
}
}
function getCallerStr($ky = 4)
{
$kw = debug_backtrace();
$kx = $kw[2];
$kz = $kw[1];
$lm = $kx['function'];
$ln = isset($kx['class']) ? $kx['class'] : '';
$lo = $kz['file'];
$lp = $kz['line'];
if ($ky == 4) {
$bl = "{$ln} {$lm} {$lo} {$lp}";
} elseif ($ky == 3) {
$bl = "{$ln} {$lm} {$lp}";
} else {
$bl = "{$ln} {$lp}";
}
return $bl;
}
function wlog($bh, $lq, $lr)
{
if (is_dir($bh)) {
$ls = date('Y-m-d', time());
$lr .= "\n";
file_put_contents($bh . "/{$lq}-{$ls}.log", $lr, FILE_APPEND);
}
}
function folder_exist($lt)
{
$bh = realpath($lt);
return ($bh !== false and is_dir($bh)) ? $bh : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($t, $lu)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $t;
}
$lv = $m['symmetric_key'];
$lw = $m['hmac_key'];
$lx = new AES_SHA($lv, $lw);
return $lx->encrypt(serialize($t), $lu);
}
function decrypt($t)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $t;
}
$lv = $m['symmetric_key'];
$lw = $m['hmac_key'];
$lx = new AES_SHA($lv, $lw);
return unserialize($lx->decrypt($t));
}
function encrypt_cookie($ly)
{
return encrypt($ly->getData(), $ly->getExpiration());
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($dj, $lz = 'DECODE', $k = '', $mn = 0)
{
$mo = 4;
$k = md5($k ? $k : UC_KEY);
$mp = md5(substr($k, 0, 16));
$mq = md5(substr($k, 16, 16));
$mr = $mo ? $lz == 'DECODE' ? substr($dj, 0, $mo) : substr(md5(microtime()), -$mo) : '';
$ms = $mp . md5($mp . $mr);
$mt = strlen($ms);
$dj = $lz == 'DECODE' ? base64_decode(substr($dj, $mo)) : sprintf('%010d', $mn ? $mn + time() : 0) . substr(md5($dj . $mq), 0, 16) . $dj;
$mu = strlen($dj);
$dk = '';
$mv = range(0, 255);
$mw = array();
for ($dq = 0; $dq <= 255; $dq++) {
$mw[$dq] = ord($ms[$dq % $mt]);
}
for ($mx = $dq = 0; $dq < 256; $dq++) {
$mx = ($mx + $mv[$dq] + $mw[$dq]) % 256;
$dm = $mv[$dq];
$mv[$dq] = $mv[$mx];
$mv[$mx] = $dm;
}
for ($my = $mx = $dq = 0; $dq < $mu; $dq++) {
$my = ($my + 1) % 256;
$mx = ($mx + $mv[$my]) % 256;
$dm = $mv[$my];
$mv[$my] = $mv[$mx];
$mv[$mx] = $dm;
$dk .= chr(ord($dj[$dq]) ^ $mv[($mv[$my] + $mv[$mx]) % 256]);
}
if ($lz == 'DECODE') {
if ((substr($dk, 0, 10) == 0 || substr($dk, 0, 10) - time() > 0) && substr($dk, 10, 16) == substr(md5(substr($dk, 26) . $mq), 0, 16)) {
return substr($dk, 26);
} else {
return '';
}
} else {
return $mr . str_replace('=', '', base64_encode($dk));
}
}

function object2array(&$mz)
{
$mz = json_decode(json_encode($mz), true);
return $mz;
}
function getKeyValues($t, $k, $bv = null)
{
if (!$bv) {
$bv = function ($bn) {
return $bn;
};
}
$no = array();
if ($t && is_array($t)) {
foreach ($t as $fn) {
if (isset($fn[$k]) && $fn[$k]) {
$cw = $fn[$k];
if ($bv) {
$cw = $bv($cw);
}
$no[] = $cw;
}
}
}
return array_unique($no);
}
if (!function_exists('indexArray')) {
function indexArray($t, $k, $eq = null)
{
$no = array();
if ($t && is_array($t)) {
foreach ($t as $fn) {
if (!isset($fn[$k]) || !$fn[$k] || !is_scalar($fn[$k])) {
continue;
}
if (!$eq) {
$no[$fn[$k]] = $fn;
} else {
if (is_string($eq)) {
$no[$fn[$k]] = $fn[$eq];
} else {
if (is_array($eq)) {
$np = [];
foreach ($eq as $bm => $bn) {
$np[$bn] = $fn[$bn];
}
$no[$fn[$k]] = $fn[$eq];
}
}
}
}
}
return $no;
}
}
if (!function_exists('groupArray')) {
function groupArray($nq, $k)
{
if (!is_array($nq) || !$nq) {
return array();
}
$t = array();
foreach ($nq as $fn) {
if (isset($fn[$k]) && $fn[$k]) {
$t[$fn[$k]][] = $fn;
}
}
return $t;
}
}
function select_keys($cs, $t)
{
$bz = [];
foreach ($cs as $k) {
if (isset($t[$k])) {
$bz[$k] = $t[$k];
} else {
$bz[$k] = '';
}
}
return $bz;
}
function un_select_keys($cs, $t)
{
$bz = [];
foreach ($t as $bm => $fn) {
if (!in_array($bm, $cs)) {
$bz[$bm] = $fn;
}
}
return $bz;
}
function copyKey($t, $nr, $ns)
{
foreach ($t as &$fn) {
$fn[$ns] = $fn[$nr];
}
return $t;
}
function addKey($t, $k, $cw)
{
foreach ($t as &$fn) {
$fn[$k] = $cw;
}
return $t;
}
function dissoc($nq, $cs)
{
if (is_array($cs)) {
foreach ($cs as $k) {
unset($nq[$k]);
}
} else {
unset($nq[$cs]);
}
return $nq;
}
function insertAt($nt, $nu, $l)
{
array_splice($nt, $nu, 0, [$l]);
return $nt;
}
function getArg($nv, $nw, $nx = '')
{
if (isset($nv[$nw])) {
return $nv[$nw];
} else {
return $nx;
}
}
function permu($am, $cn = ',')
{
$ab = [];
if (is_string($am)) {
$ny = str_split($am);
} else {
$ny = $am;
}
sort($ny);
$nz = count($ny) - 1;
$op = $nz;
$ah = 1;
$fn = implode($cn, $ny);
$ab[] = $fn;
while (true) {
$oq = $op--;
if ($ny[$op] < $ny[$oq]) {
$or = $nz;
while ($ny[$op] > $ny[$or]) {
$or--;
}

list($ny[$op], $ny[$or]) = array($ny[$or], $ny[$op]);

for ($dq = $nz; $dq > $oq; $dq--, $oq++) {
list($ny[$dq], $ny[$oq]) = array($ny[$oq], $ny[$dq]);
}
$fn = implode($cn, $ny);
$ab[] = $fn;
$op = $nz;
$ah++;
}
if ($op == 0) {
break;
}
}
return $ab;
}
function combin($no, $os, $ot = ',')
{
$dk = array();
if ($os == 1) {
return $no;
}
if ($os == count($no)) {
$dk[] = implode($ot, $no);
return $dk;
}
$ou = $no[0];
unset($no[0]);
$no = array_values($no);
$ov = combin($no, $os - 1, $ot);
foreach ($ov as $ow) {
$ow = $ou . $ot . $ow;
$dk[] = $ow;
}
unset($ov);
$ox = combin($no, $os, $ot);
foreach ($ox as $ow) {
$dk[] = $ow;
}
unset($ox);
return $dk;
}
function getExcelCol($bw)
{
$no = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($bw == 0) {
return '';
}
return getExcelCol((int) (($bw - 1) / 26)) . $no[$bw % 26];
}
function getExcelPos($cq, $bw)
{
return getExcelCol($bw) . $cq;
}
function sendJSON($t)
{
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept,X-Requested-With");
header("Content-type: application/json");
header("Access-Control-Allow-Credentials:true");
log_time("sendJSON Total", 'begin');
if (is_array($t)) {
echo json_encode($t);
} else {
echo $t;
}
exit;
}
function succ($no = array(), $oy = 'succ', $oz = 1)
{
$t = $no;
$pq = 0;
$pr = 1;
$ah = 0;
$bz = array($oy => $oz, 'errormsg' => '', 'errorfield' => '');
if (isset($no['data'])) {
$t = $no['data'];
}
if (isset($no['total_page'])) {
$bz['total_page'] = $no['total_page'];
}
if (isset($no['cur_page'])) {
$bz['cur_page'] = $no['cur_page'];
}
if (isset($no['count'])) {
$bz['count'] = $no['count'];
}
if (isset($no['res-name'])) {
$bz['res-name'] = $no['res-name'];
}
$bz['data'] = $t;
sendJSON($bz);
}
function fail($no = array(), $oy = 'succ', $ps = 0)
{
$k = $lr = '';
if (count($no) > 0) {
$cs = array_keys($no);
$k = $cs[0];
$lr = $no[$k][0];
}
$bz = array($oy => $ps, 'errormsg' => $lr, 'errorfield' => $k);
sendJSON($bz);
}
function code($no = array(), $el = 0)
{
if (is_string($el)) {
}
if ($el == 0) {
succ($no, 'code', 0);
} else {
fail($no, 'code', $el);
}
}
function ret($no = array(), $el = 0, $iv = '')
{
$my = $no;
$pt = $el;
if (is_numeric($no) || is_string($no)) {
$pt = $no;
$my = array();
if (is_array($el)) {
$my = $el;
} else {
$el = $el === 0 ? '' : $el;
$my = array($iv => array($el));
}
}
code($my, $pt);
}
function err($pu)
{
code($pu, 1);
}
function downloadExcel($pv, $dn)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $dn . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$pv->save('php://output');
}
function cacert_file()
{
return ROOT_PATH . "/fn/cacert.pem";
}
function curl($eh, $pw = 10, $px = 30, $py = '')
{
$pz = curl_init($eh);
curl_setopt($pz, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($pz, CURLOPT_CONNECTTIMEOUT, $pw);
curl_setopt($pz, CURLOPT_HEADER, 0);
curl_setopt($pz, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($pz, CURLOPT_TIMEOUT, $px);
if (file_exists(cacert_file())) {
curl_setopt($pz, CURLOPT_CAINFO, cacert_file());
}
if ($py) {
if (is_array($py)) {
$py = http_build_query($py);
}
curl_setopt($pz, CURLOPT_POST, 1);
curl_setopt($pz, CURLOPT_POSTFIELDS, $py);
}
$dk = curl_exec($pz);
if (curl_errno($pz)) {
return '';
}
curl_close($pz);
return $dk;
}
function curl_header($eh, $pw = 10, $px = 30)
{
$pz = curl_init($eh);
curl_setopt($pz, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($pz, CURLOPT_CONNECTTIMEOUT, $pw);
curl_setopt($pz, CURLOPT_HEADER, 1);
curl_setopt($pz, CURLOPT_NOBODY, 1);
curl_setopt($pz, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($pz, CURLOPT_TIMEOUT, $px);
if (file_exists(cacert_file())) {
curl_setopt($pz, CURLOPT_CAINFO, cacert_file());
}
$dk = curl_exec($pz);
if (curl_errno($pz)) {
return '';
}
return $dk;
}

function startWith($bl, $ow)
{
return strpos($bl, $ow) === 0;
}
function endWith($qr, $qs)
{
$qt = strlen($qs);
if ($qt == 0) {
return true;
}
return substr($qr, -$qt) === $qs;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $t, $qu = false, $iv = '')
{
$nq = getKeyValues($t, $k);
if (!$nq) {
return '';
}
if ($qu) {
foreach ($nq as $bm => $bn) {
$nq[$bm] = "'{$bn}'";
}
}
$bl = implode(',', $nq);
if ($iv) {
$k = $iv;
}
return " {$k} in ({$bl})";
}
function get_top_domain($eh)
{
$fj = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($fj, $eh, $qv);
if (count($qv) > 0) {
return $qv[0];
} else {
$qw = parse_url($eh);
$qx = $qw["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($qx))), $qx)) {
return $qx;
} else {
$no = explode(".", $qx);
$ah = count($no);
$qy = array("com", "net", "org", "3322");
if (in_array($no[$ah - 2], $qy)) {
$fw = $no[$ah - 3] . "." . $no[$ah - 2] . "." . $no[$ah - 1];
} else {
$fw = $no[$ah - 2] . "." . $no[$ah - 1];
}
return $fw;
}
}
}
function genID($kz)
{
list($qz, $rs) = explode(" ", microtime());
$rt = rand(0, 100);
return $kz . $rs . substr($qz, 2, 6);
}
function cguid($ru = false)
{
mt_srand((double) microtime() * 10000);
$rv = md5(uniqid(rand(), true));
return $ru ? strtoupper($rv) : $rv;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$rw = cguid();
$rx = chr(45);
$ry = chr(123) . substr($rw, 0, 8) . $rx . substr($rw, 8, 4) . $rx . substr($rw, 12, 4) . $rx . substr($rw, 16, 4) . $rx . substr($rw, 20, 12) . chr(125);
return $ry;
}
}
function randstr($jp = 6)
{
return substr(md5(rand()), 0, $jp);
}
function hashsalt($dz, $rz = '')
{
$rz = $rz ? $rz : randstr(10);
$st = md5(md5($dz) . $rz);
return [$st, $rz];
}
function gen_letters($jp = 26)
{
$ow = '';
for ($dq = 65; $dq < 65 + $jp; $dq++) {
$ow .= strtolower(chr($dq));
}
return $ow;
}
function gen_sign($at, $ao = null)
{
if ($ao == null) {
return false;
}
return strtoupper(md5(strtoupper(md5(assemble($at))) . $ao));
}
function assemble($at)
{
if (!is_array($at)) {
return null;
}
ksort($at, SORT_STRING);
$su = '';
foreach ($at as $k => $cw) {
$su .= $k . (is_array($cw) ? assemble($cw) : $cw);
}
return $su;
}
function check_sign($at, $ao = null)
{
$su = getArg($at, 'sign');
$sv = getArg($at, 'date');
$sw = strtotime($sv);
$sx = time();
$sy = $sx - $sw;
debug("check_sign : {$sx} - {$sw} = {$sy}");
if (!$sv || $sx - $sw > 60) {
debug("check_sign fail : {$sv} delta > 60");
return false;
}
unset($at['sign']);
$sz = gen_sign($at, $ao);
debug("{$su} -- {$sz}");
return $su == $sz;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$tu = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$tu = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$tu = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$tu = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$tu = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$tu = getenv("REMOTE_ADDR");
} else {
$tu = "Unknown";
}
}
}
}
}
}
return $tu;
}
function getRIP()
{
$tu = $_SERVER["REMOTE_ADDR"];
return $tu;
}
function env($k = 'DEV_MODE', $nx = '')
{
$l = getenv($k);
return $l ? $l : $nx;
}
function vpath()
{
$bh = getenv("VENDER_PATH");
if ($bh) {
return $bh;
} else {
return ROOT_PATH;
}
}
function is_docker_env()
{
return env() == 'docker';
}
function is_weixin()
{
if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
return true;
}
return false;
}
function isMobile()
{
if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
return true;
}
if (isset($_SERVER['HTTP_VIA'])) {
return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
}
if (isset($_SERVER['HTTP_USER_AGENT'])) {
$tv = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $tv) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
return true;
}
}
if (isset($_SERVER['HTTP_ACCEPT'])) {
if (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html'))) {
return true;
}
}
return false;
}
use Symfony\Component\Cache\Simple\FilesystemCache;
function cache($k, $bv, $rs = 10, $tw = 0)
{
$tx = new FilesystemCache();
if ($tw || !$tx->has($k)) {
$t = $bv();
debug("--------- no cache for [{$k}] ----------");
$tx->set($k, $t, $rs);
} else {
$t = $tx->get($k);
debug("======= data from cache [{$k}] ========");
}
return $t;
}
function cache_del($k)
{
$tx = new FilesystemCache();
$tx->delete($k);
debug("!!!!!!--------- delete cache for [{$k}] ----------!!!!!!");
}
function cache_clear()
{
$tx = new FilesystemCache();
$tx->clear();
debug("!!!!!!--------- clear all cache ----------!!!!!!");
}
function fixfn($bo)
{
foreach ($bo as $bp) {
if (!function_exists($bp)) {
eval("function {$bp}(){}");
}
}
}
function cookie_test()
{
if (isset($_COOKIE['PHPSESSID'])) {
return true;
}
return false;
}
function ms($bj)
{
return \ctx::container()->ms->get($bj);
}
function rms($bj, $q = 'rest')
{
return \ctx::container()->ms->getRest($bj, $q);
}
use db\Rest as rest;
function getMetaData($ty, $tz = array())
{
ctx::pagesize(50);
$uv = db::all('sys_objects');
$uw = array_filter($uv, function ($bn) use($ty) {
return $bn['name'] == $ty;
});
$uw = array_shift($uw);
$ux = $uw['id'];
ctx::gets('oid', $ux);
$uy = rest::getList('sys_object_item');
$uz = $uy['data'];
$vw = ['Id'];
$vx = [0.1];
$cm = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($uz as $fn) {
$bj = $fn['name'];
$vy = $fn['colname'] ? $fn['colname'] : $bj;
$c = $fn['type'];
$nx = $fn['default'];
$vz = $fn['col_width'];
$wx = $fn['readonly'] ? ture : false;
$wy = $fn['is_meta'];
if ($wy) {
$vw[] = $bj;
$vx[] = (double) $vz;
if (in_array($vy, array_keys($tz))) {
$cm[] = $tz[$vy];
} else {
$cm[] = ['data' => $vy, 'renderer' => 'html', 'readOnly' => $wx];
}
}
}
$vw[] = "InTm";
$vw[] = "St";
$vx[] = 60;
$vx[] = 10;
$cm[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$cm[] = ['data' => "_st", 'renderer' => "html"];
$wz = ['objname' => $ty];
return [$wz, $vw, $vx, $cm];
}
function auto_reg_user($xy = 'username', $xz = 'password', $bs = 'user', $yz = 0)
{
$abc = randstr(10);
$dz = randstr(6);
$t = ["{$xy}" => $abc, "{$xz}" => $dz, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($yz) {
list($dz, $rz) = hashsalt($dz);
$t[$xz] = $dz;
$t['salt'] = $rz;
} else {
$t[$xz] = md5($dz);
}
return db::save($bs, $t);
}
function refresh_token($bs, $as, $fw = '')
{
$abd = cguid();
$t = ['id' => $as, 'token' => $abd];
info("refresh_token: {$abd}");
info($t);
$ad = db::save($bs, $t);
if ($fw) {
setcookie("token", $ad['token'], time() + 3600 * 24 * 365, '/', $fw);
} else {
setcookie("token", $ad['token'], time() + 3600 * 24 * 365, '/');
}
return $ad;
}
function user_login($app, $xy = 'username', $xz = 'password', $bs = 'user', $yz = 0)
{
$pt = $app->getContainer();
$s = $pt->request;
$t = $s->getParams();
$t = select_keys([$xy, $xz], $t);
$abc = $t[$xy];
$dz = $t[$xz];
if (!$abc || !$dz) {
return NULL;
}
$ad = \db::row($bs, ["{$xy}" => $abc]);
if ($ad) {
if ($yz) {
$rz = $ad['salt'];
list($dz, $rz) = hashsalt($dz, $rz);
} else {
$dz = md5($dz);
}
if ($dz == $ad[$xz]) {
return refresh_token($bs, $ad['id']);
}
}
return NULL;
}
function uc_user_login($app, $xy = 'username', $xz = 'password')
{
log_time("uc_user_login start");
$pt = $app->getContainer();
$s = $pt->request;
$t = $s->getParams();
$t = select_keys([$xy, $xz], $t);
$abc = $t[$xy];
$dz = $t[$xz];
if (!$abc || !$dz) {
return NULL;
}
uc::init();
$bz = uc::pwd_login($abc, $dz);
if ($bz['code'] != 0) {
ret($bz['code'], $bz['message']);
}
info($bz);
$aw = $bz['data']['access_token'];
$aq = uc::user_info($aw);
$aq = $aq['data'];
$eu = [];
$abe = uc::user_role($aw, 1);
$abf = [];
if ($abe['code'] == 0) {
$abf = $abe['data']['roles'];
foreach ($abf as $k => $es) {
$eu[] = $es['name'];
}
}
$aq['roles'] = $eu;
return [$aw, $aq, $abf];
}
function check_auth($app)
{
$s = req();
$abg = false;
$abh = cfg::get('public_paths');
$ez = $s->getUri()->getPath();
if ($ez == '/') {
$abg = true;
} else {
foreach ($abh as $bh) {
if (startWith($ez, $bh)) {
$abg = true;
}
}
}
info("check_auth: {$abg} {$ez}");
if (!$abg) {
if (is_weixin()) {
$fx = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $fx);
}
ret(1, 'auth error');
}
}
function extractUserData($abi)
{
return ['githubLogin' => $abi['login'], 'githubName' => $abi['name'], 'githubId' => $abi['id'], 'repos_url' => $abi['repos_url'], 'avatar_url' => $abi['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ad, $abj = false)
{
unset($ad['passwd']);
unset($ad['salt']);
if (!$abj) {
unset($ad['token']);
}
unset($ad['access-token']);
ret($ad);
}
function cache_user($ao, $ad = null)
{
$ad = cache($ao, function () use($ad) {
return $ad;
}, 7200, $ad);
$aq = null;
if ($ad) {
$aq = getArg($ad, 'userinfo');
}
return compact('token', 'userinfo');
}
$app = new \Slim\App();
ctx::app($app);
function tpl($bf, $abk = '.html')
{
$bf = $bf . $abk;
$abl = cfg::get('tpl_prefix');
$abm = "{$abl['pc']}/{$bf}";
$abn = "{$abl['mobile']}/{$bf}";
info("tpl: {$abm} | {$abn}");
return isMobile() ? $abn : $abm;
}
function req()
{
return ctx::req();
}
function get($bj, $nx = '')
{
$s = req();
$cw = $s->getParam($bj, $nx);
if ($cw == $nx) {
$abo = ctx::gets();
if (isset($abo[$bj])) {
return $abo[$bj];
}
}
return $cw;
}
function post($bj, $nx = '')
{
$s = req();
return $s->getParam($bj, $nx);
}
function gets()
{
$s = req();
$bz = $s->getQueryParams();
$bz = array_merge($bz, ctx::gets());
return $bz;
}
function querystr()
{
$s = req();
return $s->getUri()->getQuery();
}
function posts()
{
$s = req();
return $s->getParsedBody();
}
function reqs()
{
$s = req();
return $s->getParams();
}
function uripath()
{
$s = req();
$ez = $s->getUri()->getPath();
if (!startWith($ez, '/')) {
$ez = '/' . $ez;
}
return $ez;
}
function host_str($ow)
{
$abp = '';
if (isset($_SERVER['HTTP_HOST'])) {
$abp = $_SERVER['HTTP_HOST'];
}
return " [ {$abp} ] " . $ow;
}
function debug($ow)
{
$m = \cfg::get('app');
if (isset($m['log_level']) && $m['log_level'] == 100) {
if (ctx::logger()) {
$ow = format_log_str($ow, getCallerStr(3));
ctx::logger()->debug(host_str($ow));
}
}
}
function warn($ow)
{
if (ctx::logger()) {
$ow = format_log_str($ow, getCallerStr(3));
ctx::logger()->warn(host_str($ow));
}
}
function info($ow)
{
if (ctx::logger()) {
$ow = format_log_str($ow, getCallerStr(3));
ctx::logger()->info(host_str($ow));
}
}
function format_log_str($ow, $abq = '')
{
if (is_array($ow)) {
$ow = json_encode($ow);
}
return "{$ow} [ ::{$abq} ]";
}
function ck_owner($fn)
{
$as = ctx::uid();
$iz = $fn['uid'];
debug("ck_owner: {$as} {$iz}");
return $as == $iz;
}
function _err($bj)
{
return cfg::get($bj, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bl = '', $sw = 0)
{
global $__log_time__, $__log_begin_time__;
list($qz, $rs) = explode(" ", microtime());
$abr = (double) $qz + (double) $rs;
if (!$__log_time__) {
$__log_begin_time__ = $abr;
$__log_time__ = $abr;
$bh = uripath();
debug("usetime: --- {$bh} ---");
return $abr;
}
if ($sw && $sw == 'begin') {
$abs = $__log_begin_time__;
} else {
$abs = $sw ? $sw : $__log_time__;
}
$sy = $abr - $abs;
$sy *= 1000;
debug("usetime: ---  {$sy} {$bl}  ---");
$__log_time__ = $abr;
return $abr;
}
use core\Service as ms;
$abt = $app->getContainer();
$abt['view'] = function ($pt) {
$bg = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$bg->addExtension(new \Slim\Views\TwigExtension($pt['router'], $pt['request']->getUri()));
return $bg;
};
$abt['logger'] = function ($pt) {
if (is_docker_env()) {
$abu = '/ws/log/app.log';
} else {
$abv = cfg::get('logdir');
if ($abv) {
$abu = $abv . '/app.log';
} else {
$abu = __DIR__ . '/../app.log';
}
}
$abw = ['name' => '', 'path' => $abu];
$abx = new \Monolog\Logger($abw['name']);
$abx->pushProcessor(new \Monolog\Processor\UidProcessor());
$aby = \cfg::get('app');
$ky = isset($aby['log_level']) ? $aby['log_level'] : '';
if (!$ky) {
$ky = \Monolog\Logger::INFO;
}
$abx->pushHandler(new \Monolog\Handler\StreamHandler($abw['path'], $ky));
return $abx;
};
log_time();
$abt['errorHandler'] = function ($pt) {
return function ($ew, $ex, $abz) use($pt) {
info($abz);
$acd = 'Something went wrong!';
return $pt['response']->withStatus(500)->withHeader('Content-Type', 'text/html')->write($acd);
};
};
$abt['notFoundHandler'] = function ($pt) {
if (!\ctx::isFoundRoute()) {
return function ($ew, $ex) use($pt) {
return $pt['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($ew, $ex) use($pt) {
return $pt['response'];
};
};
$abt['ms'] = function ($pt) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($iv, $l, array $at) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$ace = ROOT_PATH . '/routes';
if (folder_exist($ace)) {
$acf = dir::scan($ace, ['type' => 'file']);
foreach ($acf as $dg) {
if (basename($dg) != 'routes.php' && !endWith($dg, '.DS_Store')) {
require_once $dg;
}
}
}
$app->get('/route_list', function () {
debug("======= route list ========");
sendJSON(ctx::route_list());
});
$app->get('/cache/clear', function () {
debug("======= clear cache ========");
cache_clear();
sendJSON([]);
});
$acg = cache('blockly_routes_key', function () {
return db::all('sys_blockly', ['AND' => ['code_type' => 'route']]);
}, 86400);
foreach ($acg as $ach) {
eval($ach['phpcode']);
}
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $bj, $cy = array())
{
$app->options("/hot/{$bj}", function () {
ret([]);
});
$app->options("/hot/{$bj}/{id}", function () {
ret([]);
});
$app->get("/hot/{$bj}", function () use($cy, $bj) {
$ty = $cy['objname'];
$aci = $bj;
$ce = rest::getList($aci);
$tz = isset($cy['cols_map']) ? $cy['cols_map'] : [];
list($wz, $vw, $vx, $cm) = getMetaData($ty, $tz);
$vx[0] = 10;
$bz['data'] = ['meta' => $wz, 'list' => $ce['data'], 'colHeaders' => $vw, 'colWidths' => $vx, 'cols' => $cm];
ret($bz);
});
$app->get("/hot/{$bj}/param", function () use($cy, $bj) {
$ty = $cy['objname'];
$aci = $bj;
$ce = rest::getList($aci);
list($vw, $vx, $cm) = getHotColMap1($aci);
$wz = ['objname' => $ty];
$vx[0] = 10;
$bz['data'] = ['meta' => $wz, 'list' => [], 'colHeaders' => $vw, 'colWidths' => $vx, 'cols' => $cm];
ret($bz);
});
$app->post("/hot/{$bj}", function () use($cy, $bj) {
$aci = $bj;
$ce = rest::postData($aci);
ret($ce);
});
$app->put("/hot/{$bj}/{id}", function ($s, $bd, $nv) use($cy, $bj) {
$aci = $bj;
$t = ctx::data();
if (isset($t['trans-word']) && isset($t[$t['trans-from']])) {
$acj = $t['trans-from'];
$ack = $t['trans-to'];
$cw = util\Pinyin::get($t[$acj]);
$t[$ack] = $cw;
}
ctx::data($t);
$ce = rest::putData($aci, $nv['id']);
ret($ce);
});
}
function getHotColMap()
{
$aci = 'bom_part_params';
ctx::pagesize(50);
$ce = rest::getList($aci);
$acl = getKeyValues($ce['data'], 'id');
$at = indexArray($ce['data'], 'id');
$acm = db::all('bom_part_param_prop', ['AND' => ['part_param_id' => $acl]]);
$acm = groupArray($acm, 'id');
$hv = db::all('bom_part_param_opt', ['AND' => ['param_id' => $acl]]);
$hv = groupArray($hv, 'param_prop_id');
$tz = [];
foreach ($hv as $k => $dt) {
$acn = '';
$aco = 0;
$acp = $acm[$k];
foreach ($acp as $bm => $acq) {
if ($acq['name'] == 'value') {
$aco = $acq['part_param_id'];
}
}
$acn = $at[$aco]['name'];
if ($aco) {
}
if ($acn) {
$tz[$acn] = ['data' => $acn, 'type' => 'autocomplete', 'strict' => false, 'source' => getKeyValues($dt, 'option')];
}
}
$t = ['rows' => $ce, 'pids' => $acl, 'props' => $acm, 'opts' => $hv, 'cols_map' => $tz];
$tz = [];
return $tz;
}
function getHotColMap1($aci)
{
$acr = $aci . '_param';
$acs = $aci . '_opt';
$act = $aci . '_opt_ext';
ctx::pagesize(50);
ctx::gets('pid', 6);
$ce = rest::getList($acr);
$acl = getKeyValues($ce['data'], 'id');
$at = indexArray($ce['data'], 'id');
$hv = db::all($acs, ['AND' => ['pid' => $acl]]);
$hv = indexArray($hv, 'id');
$acl = array_keys($hv);
$acu = db::all($act, ['AND' => ['pid' => $acl]]);
$acu = groupArray($acu, 'pid');
$vw = [];
$vx = [];
$cm = [];
foreach ($at as $k => $acv) {
$vw[] = $acv['label'];
$vx[] = $acv['width'];
$cm[$acv['name']] = ['data' => $acv['name'], 'renderer' => 'html'];
}
foreach ($acu as $k => $dt) {
$acn = '';
$aco = 0;
$acw = $hv[$k];
$acx = $acw['pid'];
$acv = $at[$acx];
$acy = $acv['label'];
$acn = $acv['name'];
if ($aco) {
}
if ($acn) {
$cm[$acn] = ['data' => $acn, 'type' => 'autocomplete', 'strict' => false, 'source' => getKeyValues($dt, 'option')];
}
}
$cm = array_values($cm);
return [$vw, $vx, $cm];
$t = ['rows' => $ce, 'pids' => $acl, 'props' => $acm, 'opts' => $hv, 'cols_map' => $tz];
$tz = [];
return $tz;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $bj, $cy = array())
{
$aci = $bj;
$acz = "{$bj}_ext";
$app->get("/hot/{$bj}", function () use($aci, $acz) {
$jk = get('oid');
$aco = get('pid');
$bu = "select * from `{$aci}` pp join `{$acz}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$jk} and pp.pid={$aco}";
$ce = db::query($bu);
$t = groupArray($ce, 'name');
$vw = ['Id', 'Oid', 'RowNum'];
$vx = [5, 5, 5];
$cm = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ab = [];
foreach ($t as $bm => $bn) {
$vw[] = $bn[0]['label'];
$vx[] = $bn[0]['col_width'];
$cm[] = ['data' => $bm, 'renderer' => 'html'];
$ade = [];
foreach ($bn as $k => $fn) {
$ab[$fn['_rownum']][$bm] = $fn['option'];
if ($bm == 'value') {
if (!isset($ab[$fn['_rownum']]['id'])) {
$ab[$fn['_rownum']]['id'] = $fn['id'];
$ab[$fn['_rownum']]['oid'] = $jk;
$ab[$fn['_rownum']]['_rownum'] = $fn['_rownum'];
}
}
}
}
$ab = array_values($ab);
$bz['data'] = ['list' => $ab, 'colHeaders' => $vw, 'colWidths' => $vx, 'cols' => $cm];
ret($bz);
});
$app->get("/hot/{$bj}_addprop", function () use($aci, $acz) {
$jk = get('oid');
$aco = get('pid');
$adf = get('propname');
if ($adf != 'value' && !checkOptPropVal($jk, $aco, 'value', $aci, $acz)) {
addOptProp($jk, $aco, 'value', $aci, $acz);
}
if (!checkOptPropVal($jk, $aco, $adf, $aci, $acz)) {
addOptProp($jk, $aco, $adf, $aci, $acz);
}
ret([11]);
});
$app->options("/hot/{$bj}", function () {
ret([]);
});
$app->options("/hot/{$bj}/{id}", function () {
ret([]);
});
$app->post("/hot/{$bj}", function () use($aci, $acz) {
$t = ctx::data();
$aco = $t['pid'];
$jk = $t['oid'];
$adg = $t['_rownum'];
$acq = db::row($aci, ['AND' => ['oid' => $jk, 'pid' => $aco, 'name' => 'value']]);
if (!$acq) {
addOptProp($jk, $aco, 'value', $aci, $acz);
}
$adh = $acq['id'];
$adi = db::obj()->max($acz, '_rownum', ['pid' => $adh]);
$t = ['oid' => $jk, 'pid' => $adh, '_rownum' => $adi + 1];
db::save($acz, $t);
$bz = ['oid' => $jk, '_rownum' => $adg, 'prop' => $acq, 'maxrow' => $adi];
ret($bz);
});
$app->put("/hot/{$bj}/{id}", function ($s, $bd, $nv) use($acz, $aci) {
$t = ctx::data();
$aco = $t['pid'];
$jk = $t['oid'];
$adg = $t['_rownum'];
$ao = $t['token'];
$as = $t['uid'];
$fn = dissoc($t, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($fn);
$k = key($fn);
$cw = $fn[$k];
$acq = db::row($aci, ['AND' => ['pid' => $aco, 'oid' => $jk, 'name' => $k]]);
info("{$aco} {$jk} {$k}");
$adh = $acq['id'];
$adj = db::obj()->has($acz, ['AND' => ['pid' => $adh, '_rownum' => $adg]]);
if ($adj) {
debug("has cell ...");
$bu = "update {$acz} set `option`='{$cw}' where _rownum={$adg} and pid={$adh}";
debug($bu);
db::exec($bu);
} else {
debug("has no cell ...");
$t = ['oid' => $jk, 'pid' => $adh, '_rownum' => $adg, 'option' => $cw];
db::save($acz, $t);
}
$bz = ['item' => $fn, 'oid' => $jk, '_rownum' => $adg, 'key' => $k, 'val' => $cw, 'prop' => $acq, 'sql' => $bu];
ret($bz);
});
}
function checkOptPropVal($jk, $aco, $bj, $aci, $acz)
{
return db::obj()->has($aci, ['AND' => ['name' => $bj, 'oid' => $jk, 'pid' => $aco]]);
}
function addOptProp($jk, $aco, $adf, $aci, $acz)
{
$bj = Pinyin::get($adf);
$t = ['oid' => $jk, 'pid' => $aco, 'label' => $adf, 'name' => $bj];
$acq = db::save($aci, $t);
$t = ['_rownum' => 1, 'oid' => $jk, 'pid' => $acq['id']];
db::save($acz, $t);
return $acq;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$adk = \cfg::load('mid');
if ($adk) {
foreach ($adk as $bm => $m) {
$adl = "\\{$bm}";
debug("load mid: {$adl}");
$app->add(new $adl());
}
}
if (file_exists(ROOT_PATH . DS . 'lib/mid/MyAuthMid.php')) {
$app->add(new \mid\MyAuthMid());
} else {
$app->add(new \mid\AuthMid());
}
$app->add(new \pavlakis\cli\CliRequest());
log_time("MID END");
}
namespace {
session_start();
$app->run();
}
