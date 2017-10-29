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
public static function config($k = null)
{
$p = new \Symfony\Component\DependencyInjection\ContainerBuilder();
$o = array(ROOT_PATH . '/cfg/config');
$a = new \Symfony\Component\DependencyInjection\Loader\YamlFileLoader($p, new \Symfony\Component\Config\FileLocator($o));
if (folder_exist($o[0])) {
$q = \Lead\Dir\Dir::scan($o[0], ['type' => 'file']);
foreach ($q as $r) {
debug("load config: {$r}");
$a->load($r);
}
}
$p->compile();
$s = $p->getParameterBag()->all();
if ($k) {
$t = new \core\Dot($s);
$u = $t[$k];
} else {
$u = $s;
}
$v = $p->resolveEnvPlaceholders($u, true);
return $v;
}
public static function get_db_cfg($k = 'use_db')
{
$w = self::get($k, 'db.yml');
return self::get($w, 'db.yml');
}
public static function get_rest_prefix()
{
$x = \cfg::get('rest_prefix');
return $x ? $x : '/rest';
}
public static function get_redis_cfg()
{
$y = 'redis';
if (is_docker_env()) {
$y = 'docker_redis';
}
return self::get($y, 'db.yml');
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
public static function init($z)
{
debug(" -- ctx init -- ");
self::$_user_tbl = \cfg::get('user_tbl_name');
self::$_user = self::getTokenUser(self::$_user_tbl, $z);
self::$_rest_prefix = \cfg::get_rest_prefix();
self::$_uripath = uripath($z);
$ab = self::getReqData();
if (!self::isAdminRest() && self::$_user) {
$ab['uid'] = getArg(self::$_user, 'id');
}
$ab['_uptm'] = date('Y-m-d H:i:s');
$ab['uniqid'] = uniqid();
self::$_data = $ab;
$ac = get('page', 1);
$ad = $ac <= 1 ? 1 : $ac - 1;
$ae = $ac + 1;
$af = get('size', 10);
$ag = ($ac - 1) * $af;
$ac = ['page' => $ac, 'prepage' => $ad, 'nextpage' => $ae, 'pagesize' => $af, 'offset' => $ag, 'list' => [], 'totalpage' => 0, 'count' => 0, 'isFirstPage' => false, 'isLastPage' => false];
self::$_page = $ac;
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
$ah = ctx::router()->getRoutes();
$ai = [];
foreach ($ah as $k => $aj) {
$ai[] = ['methods' => $aj->getMethods(), 'name' => $aj->getname(), 'pattern' => $aj->getPattern(), 'groups' => $aj->getgroups(), 'arguments' => $aj->getarguments()];
}
return $ai;
}
public static function logger()
{
return self::container()['logger'];
}
public static function user($ak = null)
{
if ($ak) {
self::$_user = $ak;
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
$ak = self::user();
return isset($ak['roles']) ? $ak['roles'] : [];
}
public static function isRest()
{
return startWith(self::$_uripath, self::$_rest_prefix);
}
public static function isAdmin()
{
$al = is_array(self::roles()) && in_array('admin', self::roles());
debug("is_admin: {$al}");
return $al;
}
public static function isAdminRest()
{
return startWith(self::$_uripath, self::$_rest_prefix . '_admin');
}
public static function retType()
{
$c = '';
$am = self::getReqData();
if (isset($am['ret-type'])) {
$c = $am['ret-type'];
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
$an = isset($m['mode']) ? $m['mode'] : 'normal';
info("app_mode {$an}");
return $an;
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
public static function data($ab = null)
{
if ($ab) {
self::$_data = $ab;
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
public static function pagesize($ao = null)
{
if ($ao) {
self::$_page['pagesize'] = $ao;
}
return self::$_page['pagesize'];
}
public static function offset()
{
return self::$_page['offset'];
}
public static function count($ap)
{
self::$_page['count'] = $ap;
$aq = $ap / self::$_page['pagesize'];
$aq = ceil($aq);
self::$_page['totalpage'] = $aq;
if (self::$_page['page'] == '1') {
self::$_page['isFirstPage'] = true;
}
if (!$aq || self::$_page['page'] == $aq) {
self::$_page['isLastPage'] = true;
}
if (self::$_page['nextpage'] > $aq) {
self::$_page['nextpage'] = $aq ? $aq : 1;
}
$ar = self::$_page['page'] - 4;
$as = self::$_page['page'] + 4;
if ($as > $aq) {
$as = $aq;
$ar = $ar - ($as - $aq);
}
if ($ar <= 1) {
$ar = 1;
}
if ($as - $ar < 8 && $as < $aq) {
$as = $ar + 8;
}
$as = $as ? $as : 1;
$ai = range($ar, $as);
self::$_page['list'] = $ai;
return self::$_page['count'];
}
public static function limit()
{
return [self::offset(), self::pagesize()];
}
public static function getReqData()
{
$z = req();
return $z->getParams();
$at = isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : '';
$at = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : $at;
$ab = '';
if (!empty($_POST)) {
$ab = $_POST;
} else {
$au = file_get_contents("php://input");
if ($au) {
if (strpos($at, 'application/x-www-form-urlencoded') !== false) {
parse_str($au, $av);
$ab = $av;
} else {
if (strpos($at, 'application/json') !== false) {
$ab = json_decode($au, true);
}
}
}
}
return $ab;
}
public static function getToken($z, $k = 'token')
{
$aw = $z->getParam($k);
$ab = $z->getParams();
if (!$aw) {
$aw = getArg($ab, $k);
}
if (!$aw) {
$aw = getArg($_COOKIE, $k);
}
return $aw;
}
public static function getUcTokenUser($aw)
{
if (!$aw) {
return null;
}
$ax = cache($aw);
$ay = $ax['userinfo'] ? $ax['userinfo'] : null;
$ay['id'] = $ay['uid'] = getArg($ay, 'user_id');
return $ay;
}
public static function getTokenUser($az, $z)
{
$bc = $z->getParam('uid');
$ak = null;
$bd = $z->getParams();
$be = self::check_appid($bd);
if ($be && check_sign($bd, $be)) {
debug("appkey: {$be}");
$ak = ['id' => $bc, 'role' => 'admin'];
} else {
if (self::isStateless()) {
debug("isStateless");
$ak = ['id' => $bc, 'role' => 'user'];
} else {
$aw = self::getToken($z);
$bf = \cfg::get('use_ucenter_oauth');
if ($bf) {
return self::getUcTokenUser($aw);
}
$bg = self::getToken($z, 'access_token');
if (self::isEnableSso()) {
debug("getTokenUserBySso");
$ak = self::getTokenUserBySso($aw);
} else {
debug("get from db");
if ($aw) {
$ak = cache($aw);
$ak = $ak['user'];
} else {
if ($bg) {
$ak = self::getAccessTokenUser($az, $bg);
}
}
if ($ak) {
$ak['roles'] = [getArg($ak, 'role')];
}
}
}
}
return $ak;
}
public static function check_appid($bd)
{
$bh = getArg($bd, 'appid');
if ($bh) {
$m = cfg::get('support_service_list', 'service');
if (isset($m[$bh])) {
debug("appid: {$bh} ok");
return $m[$bh];
}
}
debug("appid: {$bh} not ok");
return '';
}
public static function getTokenUserBySso($aw)
{
$ak = ms('sso')->getuserinfo(['token' => $aw])->json();
return $ak;
}
public static function getAccessTokenUser($az, $bg)
{
$bi = \db::row('oauth_access_tokens', ['access_token' => $bg]);
if ($bi) {
$bj = strtotime($bi['expires']);
if ($bj - time() > 0) {
$ak = \db::row($az, ['id' => $bi['user_id']]);
}
}
return $ak;
}
public static function user_tbl($bk = null)
{
if ($bk) {
self::$_user_tbl = $bk;
}
return self::$_user_tbl;
}
public static function render($bl, $bm, $bn, $ab)
{
$bo = new \Slim\Views\Twig($bm, ['cache' => false]);
self::$_foundRoute = true;
return $bo->render($bl, $bn, $ab);
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
$bp = str_replace(self::$_rest_prefix, '', self::uri());
$bq = explode('/', $bp);
$br = getArg($bq, 1, '');
$bs = getArg($bq, 2, '');
return [$br, $bs];
}
public static function rest_select_add($bt = '')
{
if ($bt) {
self::$_rest_select_add = $bt;
}
return self::$_rest_select_add;
}
public static function rest_join_add($bt = '')
{
if ($bt) {
self::$_rest_join_add = $bt;
}
return self::$_rest_join_add;
}
public static function rest_extra_data($ab = '')
{
if ($ab) {
self::$_rest_extra_data = $ab;
}
return self::$_rest_extra_data;
}
public static function global_view_data($ab = '')
{
if ($ab) {
self::$_global_view_data = $ab;
}
return self::$_global_view_data;
}
public static function gets($bu = '', $bv = '')
{
if (!$bu) {
return self::$_gets;
}
if (!$bv) {
return self::$_gets[$bu];
}
if ($bv == '_clear') {
$bv = '';
}
self::$_gets[$bu] = $bv;
return self::$_gets;
}
}
use Medoo\Medoo;
if (!function_exists('fixfn')) {
function fixfn($bw)
{
foreach ($bw as $bx) {
if (!function_exists($bx)) {
eval("function {$bx}(){}");
}
}
}
}
if (!class_exists('cfg')) {
class cfg
{
public static function get_db_cfg()
{
return array('database_type' => 'mysql', 'database_name' => 'myapp_dev', 'server' => 'mysql', 'username' => 'root', 'password' => '123456', 'charset' => 'utf8');
}
}
}
$bw = array('debug');
fixfn($bw);
class db
{
private static $_db_list;
private static $_db_default;
private static $_db;
private static $_dbc;
private static $_ins;
private static $tbl_desc = array();
public static function init($m, $by = true)
{
self::init_db($m, $by);
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
public static function init_db($m, $by = true)
{
self::$_dbc = self::get_db_cfg($m);
$bz = self::$_dbc['database_name'];
self::$_db_list[$bz] = self::new_db(self::$_dbc);
if ($by) {
self::use_db($bz);
}
}
public static function use_db($bz)
{
self::$_db = self::$_db_list[$bz];
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
public static function desc_sql($cd)
{
if (self::db_type() == 'mysql') {
return "desc {$cd}";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$cd}'";
} else {
return '';
}
}
}
public static function table_cols($br)
{
$ce = self::$tbl_desc;
if (!isset($ce[$br])) {
$cf = self::desc_sql($br);
if ($cf) {
$ce[$br] = self::query($cf);
self::$tbl_desc = $ce;
debug("---------------- cache not found : {$br}");
} else {
debug("empty desc_sql for: {$br}");
}
}
if (!isset($ce[$br])) {
return array();
} else {
return self::$tbl_desc[$br];
}
}
public static function col_array($br)
{
$cg = function ($bv) use($br) {
return $br . '.' . $bv;
};
return getKeyValues(self::table_cols($br), 'Field', $cg);
}
public static function valid_table_col($br, $ch)
{
$ci = self::table_cols($br);
foreach ($ci as $cj) {
if ($cj['Field'] == $ch) {
$c = $cj['Type'];
return is_string_column($cj['Type']);
}
}
return false;
}
public static function tbl_data($br, $ab)
{
$ci = self::table_cols($br);
$v = [];
foreach ($ci as $cj) {
$ck = $cj['Field'];
if (isset($ab[$ck])) {
$v[$ck] = $ab[$ck];
}
}
return $v;
}
public static function test()
{
$cf = "select * from tags limit 10";
$cl = self::obj()->query($cf)->fetchAll(\PDO::FETCH_ASSOC);
var_dump($cl);
}
public static function has_st($br, $cm)
{
$cn = '_st';
return isset($cm[$cn]) || isset($cm[$br . '.' . $cn]);
}
public static function getWhere($br, $co)
{
$cn = '_st';
if (!self::valid_table_col($br, $cn)) {
return $co;
}
$cn = $br . '._st';
if (is_array($co)) {
$cp = array_keys($co);
$cq = preg_grep("/^AND\\s*#?\$/i", $cp);
$cr = preg_grep("/^OR\\s*#?\$/i", $cp);
$cs = array_diff_key($co, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
$cm = [];
if ($cs != array()) {
$cm = $cs;
if (!self::has_st($br, $cm)) {
$co[$cn] = 1;
$co = ['AND' => $co];
}
}
if (!empty($cq)) {
$l = array_values($cq);
$cm = $co[$l[0]];
if (!self::has_st($br, $cm)) {
$co[$l[0]][$cn] = 1;
}
}
if (!empty($cr)) {
$l = array_values($cr);
$cm = $co[$l[0]];
if (!self::has_st($br, $cm)) {
$co[$l[0]][$cn] = 1;
}
}
if (!isset($co['AND']) && !self::has_st($br, $cm)) {
$co['AND'][$cn] = 1;
}
}
return $co;
}
public static function all_sql($br, $co = array(), $ct = '*', $cu = null)
{
$cv = [];
if ($cu) {
$cf = self::obj()->selectContext($br, $cv, $cu, $ct, $co);
} else {
$cf = self::obj()->selectContext($br, $cv, $ct, $co);
}
return $cf;
}
public static function all($br, $co = array(), $ct = '*', $cu = null)
{
$co = self::getWhere($br, $co);
if ($cu) {
$cl = self::obj()->select($br, $cu, $ct, $co);
} else {
$cl = self::obj()->select($br, $ct, $co);
}
return $cl;
}
public static function count($br, $co = array('_st' => 1))
{
$co = self::getWhere($br, $co);
return self::obj()->count($br, $co);
}
public static function row_sql($br, $co = array(), $ct = '*', $cu = '')
{
return self::row($br, $co, $ct, $cu, true);
}
public static function row($br, $co = array(), $ct = '*', $cu = '', $cw = null)
{
$co = self::getWhere($br, $co);
if (!isset($co['LIMIT'])) {
$co['LIMIT'] = 1;
}
if ($cu) {
if ($cw) {
return self::obj()->selectContext($br, $cu, $ct, $co);
}
$cl = self::obj()->select($br, $cu, $ct, $co);
} else {
if ($cw) {
return self::obj()->selectContext($br, $ct, $co);
}
$cl = self::obj()->select($br, $ct, $co);
}
if ($cl) {
return $cl[0];
} else {
return null;
}
}
public static function one($br, $co = array(), $ct = '*', $cu = '')
{
$cx = self::row($br, $co, $ct, $cu);
$cy = '';
if ($cx) {
$cz = array_keys($cx);
$cy = $cx[$cz[0]];
}
return $cy;
}
public static function parseUk($br, $de, $ab)
{
$df = true;
if (is_array($de)) {
foreach ($de as $dg) {
if (!isset($ab[$dg])) {
$df = false;
} else {
$dh[$dg] = $ab[$dg];
}
}
} else {
if (!isset($ab[$de])) {
$df = false;
} else {
$dh = [$de => $ab[$de]];
}
}
$di = false;
if ($df) {
if (!self::obj()->has($br, ['AND' => $dh])) {
$di = true;
}
} else {
$di = true;
}
return [$dh, $di];
}
public static function save($br, $ab, $de = 'id')
{
list($dh, $di) = self::parseUk($br, $de, $ab);
if ($di) {
debug("insert {$br} : " . json_encode($ab));
self::obj()->insert($br, $ab);
$ab['id'] = self::obj()->id();
} else {
debug("update {$br} " . json_encode($dh));
self::obj()->update($br, $ab, ['AND' => $dh]);
}
return $ab;
}
public static function update($br, $ab, $co)
{
self::obj()->update($br, $ab, $co);
}
public static function exec($cf)
{
return self::obj()->query($cf);
}
public static function query($cf)
{
return self::obj()->query($cf)->fetchAll(\PDO::FETCH_ASSOC);
}
public static function queryRow($cf)
{
$cl = self::query($cf);
if ($cl) {
return $cl[0];
} else {
return null;
}
}
public static function queryOne($cf)
{
$cx = self::queryRow($cf);
return self::oneVal($cx);
}
public static function oneVal($cx)
{
$cy = '';
if ($cx) {
$cz = array_keys($cx);
$cy = $cx[$cz[0]];
}
return $cy;
}
public static function updateBatch($br, $ab)
{
$dj = $br;
if (!is_array($ab) || empty($dj)) {
return FALSE;
}
$cf = "UPDATE `{$dj}` SET";
foreach ($ab as $bs => $cx) {
foreach ($cx as $k => $u) {
$dk[$k][] = "WHEN {$bs} THEN {$u}";
}
}
foreach ($dk as $k => $u) {
$cf .= ' `' . trim($k, '`') . '`=CASE id ' . join(' ', $u) . ' END,';
}
$cf = trim($cf, ',');
$cf .= ' WHERE id IN(' . join(',', array_keys($ab)) . ')';
return self::query($cf);
}
}
class fcache
{
const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
const CLEAR_ALL_KEY = 'CLEAR_ALL';
static $_instance = null;
protected $_options = array('cache_dir' => './cache', 'file_locking' => true, 'file_name_prefix' => 'cache', 'cache_file_umask' => 0777, 'file_life' => 100000);
static function &getInstance($dl = array())
{
if (self::$_instance === null) {
self::$_instance = new self($dl);
}
return self::$_instance;
}
static function &setOptions($dl = array())
{
return self::getInstance($dl);
}
private function __construct($dl = array())
{
if ($this->_options['cache_dir'] !== null) {
$bm = rtrim($this->_options['cache_dir'], '/') . '/';
$this->_options['cache_dir'] = $bm;
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
$dm =& self::getInstance();
if (!is_dir($l)) {
exit('file_cache: ' . $l . ' 不是一个有效路径 ');
}
if (!is_writable($l)) {
exit('file_cache: 路径 "' . $l . '" 不可写');
}
$l = rtrim($this->_options['cache_dir'], '/') . '/';
$dm->_options['cache_dir'] = $l;
}
static function save($ab, $bs = null, $dn = null)
{
$dm =& self::getInstance();
if (!$bs) {
if ($dm->_id) {
$bs = $dm->_id;
} else {
exit('file_cache:save() id 不能为空!');
}
}
$do = time();
if ($dn) {
$ab[self::FILE_LIFE_KEY] = $do + $dn;
} elseif ($dn != 0) {
$ab[self::FILE_LIFE_KEY] = $do + $dm->_options['file_life'];
}
$r = $dm->_file($bs);
$ab = "\n" . " // mktime: " . $do . "\n" . " return " . var_export($ab, true) . "\n?>";
$dp = $dm->_filePutContents($r, $ab);
return $dp;
}
static function load($bs)
{
$dm =& self::getInstance();
$do = time();
if (!$dm->test($bs)) {
return false;
}
$dq = $dm->_file(self::CLEAR_ALL_KEY);
$r = $dm->_file($bs);
if (is_file($dq) && filemtime($dq) > filemtime($r)) {
return false;
}
$ab = $dm->_fileGetContents($r);
if (empty($ab[self::FILE_LIFE_KEY]) || $do < $ab[self::FILE_LIFE_KEY]) {
unset($ab[self::FILE_LIFE_KEY]);
return $ab;
}
return false;
}
protected function _filePutContents($r, $dr)
{
$dm =& self::getInstance();
$ds = false;
$dt = @fopen($r, 'ab+');
if ($dt) {
if ($dm->_options['file_locking']) {
@flock($dt, LOCK_EX);
}
fseek($dt, 0);
ftruncate($dt, 0);
$du = @fwrite($dt, $dr);
if (!($du === false)) {
$ds = true;
}
@fclose($dt);
}
@chmod($r, $dm->_options['cache_file_umask']);
return $ds;
}
protected function _file($bs)
{
$dm =& self::getInstance();
$dv = $dm->_idToFileName($bs);
return $dm->_options['cache_dir'] . $dv;
}
protected function _idToFileName($bs)
{
$dm =& self::getInstance();
$dm->_id = $bs;
$x = $dm->_options['file_name_prefix'];
$ds = $x . '---' . $bs;
return $ds;
}
static function test($bs)
{
$dm =& self::getInstance();
$r = $dm->_file($bs);
if (!is_file($r)) {
return false;
}
return true;
}
protected function _fileGetContents($r)
{
if (!is_file($r)) {
return false;
}
return include $r;
}
static function clear()
{
$dm =& self::getInstance();
$dm->save('CLEAR_ALL', self::CLEAR_ALL_KEY);
}
static function del($bs)
{
$dm =& self::getInstance();
if (!$dm->test($bs)) {
return false;
}
$r = $dm->_file($bs);
return unlink($r);
}
}
class mdb
{
private static $_client;
private static $_db;
private static $_ins;
public static function obj($bz = 'myapp_dev')
{
if (!self::$_client) {
self::$_client = new \Sokil\Mongo\Client();
}
if (!self::$_db) {
self::$_db = self::$_client->{$bz};
}
return self::$_db;
}
public static function test()
{
$bc = 1;
$dw = self::obj()->blogs;
$dx = $dw->find()->findAll();
$ab = object2array($dx);
$dy = 1;
foreach ($ab as $bu => $dz) {
unset($dz['_id']);
unset($dz['tid']);
unset($dz['tags']);
if (isset($dz['_intm'])) {
$dz['_intm'] = date('Y-m-d H:i:s', $dz['_intm']['sec']);
}
if (isset($dz['_uptm'])) {
$dz['_uptm'] = date('Y-m-d H:i:s', $dz['_uptm']['sec']);
}
$dz['uid'] = $bc;
$v = db::save('blogs', $dz);
$dy++;
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
self::$_client = $ef = new Predis\Client(cfg::get_redis_cfg());
}
return self::$_client;
}
}
class uc
{
static $UC_HOST = 'http://uc.xxx.com/';
const API = array('user' => '/api/user', 'accessToken' => '/api/oauth/accessToken', 'userRole' => '/api/user/role', 'createDomain' => "/api/domain", 'finduser' => '/api/users', 'userAccessToken' => '/api/user/accessToken', 'userdomain' => '/api/user/domain');
static $code_user = null;
static $pwd_user = null;
static $id_user = null;
static $user_info = null;
static $user_role = null;
private static $oauth_cfg;
public static function init($eg = array())
{
$m = cfg::get('oauth', 'oauth')['uc'];
$m['host'] = env('UC_HOST', $m['host']);
$m['clientId'] = env('UC_CLIENT_ID', $m['clientId']);
$m['clientSecret'] = env('UC_CLIENT_SECRET', $m['clientSecret']);
$m['redirectUri'] = env('UC_REDIRECT_URI', $m['redirectUri']);
$m['username'] = env('UC_USERNAME', $m['username']);
$m['passwd'] = env('UC_PASSWD', $m['passwd']);
self::$oauth_cfg = $m;
if (isset($eg['host'])) {
self::$UC_HOST = $eg['host'];
}
}
public static function makeUrl($bp, $bd = '')
{
return self::$oauth_cfg['host'] . $bp . ($bd ? '?' . $bd : '');
}
public static function pwd_login($eh = null, $ei = null, $ej = null, $ek = null)
{
$el = $eh ? $eh : self::$oauth_cfg['username'];
$em = $ei ? $ei : self::$oauth_cfg['passwd'];
$en = $ej ? $ej : self::$oauth_cfg['clientId'];
$eo = $ek ? $ek : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $en, 'client_secret' => $eo, 'grant_type' => 'password', 'username' => $el, 'password' => $em];
$ep = self::makeUrl(self::API['accessToken']);
$eq = curl($ep, 10, 30, $ab);
$v = json_decode($eq, true);
self::_set_pwd_user($v);
return $v;
}
public static function get_admin_token($er = array())
{
if (isset($er['access_token'])) {
$bg = $er['access_token'];
} else {
$v = self::pwd_login();
$bg = $v['data']['access_token'];
}
return $bg;
}
public static function id_login($bs, $ej = null, $ek = null, $es = array())
{
$en = $ej ? $ej : self::$oauth_cfg['clientId'];
$eo = $ek ? $ek : self::$oauth_cfg['clientSecret'];
$bg = self::get_admin_token($es);
$ab = ['client_id' => $en, 'client_secret' => $eo, 'grant_type' => 'id', 'access_token' => $bg, 'id' => $bs];
$ep = self::makeUrl(self::API['userAccessToken']);
$eq = curl($ep, 10, 30, $ab);
$v = json_decode($eq, true);
self::_set_id_user($v);
return $v;
}
public static function authurl($bh, $et, $bg)
{
$eu = self::$oauth_cfg['host'] . "/api/sso/redirect?access_token={$bg}&app_id={$bh}&domain_id={$et}";
return $eu;
}
public static function code_login($ev, $ew = null, $ej = null, $ek = null)
{
$ex = $ew ? $ew : self::$oauth_cfg['redirectUri'];
$en = $ej ? $ej : self::$oauth_cfg['clientId'];
$eo = $ek ? $ek : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $en, 'client_secret' => $eo, 'grant_type' => 'authorization_code', 'redirect_uri' => $ex, 'code' => $ev];
$ep = self::makeUrl(self::API['accessToken']);
$eq = curl($ep, 10, 30, $ab);
$v = json_decode($eq, true);
self::_set_code_user($v);
return $v;
}
public static function user_info($bg)
{
$ep = self::makeUrl(self::API['user'], 'access_token=' . $bg);
$eq = curl($ep);
$v = json_decode($eq, true);
self::_set_user_info($v);
return $v;
}
public static function reg_user($el, $ei = '123456', $es = array())
{
$bg = self::get_admin_token($es);
$ab = ['username' => $el, 'password' => $ei, 'access_token' => $bg];
$ep = self::makeUrl(self::API['user']);
$eq = curl($ep, 10, 30, $ab);
$ey = json_decode($eq, true);
return $ey;
}
public static function register_user($el, $ei = '123456')
{
return self::reg_user($el, $ei);
}
public static function find_user($er = array())
{
$bg = self::get_admin_token($er);
$bd = 'access_token=' . $bg;
if (isset($er['username'])) {
$bd .= '&username=' . $er['username'];
}
if (isset($er['phone'])) {
$bd .= '&phone=' . $er['phone'];
}
$ep = self::makeUrl(self::API['finduser'], $bd);
$eq = curl($ep, 10, 30);
$ey = json_decode($eq, true);
return $ey;
}
public static function set_user_role($bg, $et, $ez, $fg = 'guest')
{
$ab = ['access_token' => $bg, 'domain_id' => $et, 'user_id' => $ez, 'role_name' => $fg];
$ep = self::makeUrl(self::API['userRole']);
$eq = curl($ep, 10, 30, $ab);
return json_decode($eq, true);
}
public static function user_role($bg, $et)
{
$ab = ['access_token' => $bg, 'domain_id' => $et];
$ep = self::makeUrl(self::API['userRole']);
$ep = "{$ep}?access_token={$bg}&domain_id={$et}";
$eq = curl($ep, 10, 30);
$v = json_decode($eq, true);
self::_set_user_role($v);
return $v;
}
public static function has_role($fh)
{
if (self::$user_role && isset(self::$user_role['roles'])) {
$fi = self::$user_role['roles'];
foreach ($fi as $k => $fg) {
if ($fg['name'] == $fh) {
return true;
}
}
}
return false;
}
public static function _set_pwd_user($v)
{
if ($v['code'] == 0) {
self::$pwd_user = $v['data'];
}
}
public static function _set_code_user($v)
{
if ($v['code'] == 0) {
self::$code_user = $v['data'];
}
}
public static function _set_id_user($v)
{
if ($v['code'] == 0) {
self::$id_user = $v['data'];
}
}
public static function _set_user_info($v)
{
if ($v['code'] == 0) {
self::$user_info = $v['data'];
}
}
public static function _set_user_role($v)
{
if ($v['code'] == 0) {
self::$user_role = $v['data'];
}
}
}
class vld
{
public static function test($br, $ab)
{
}
public static function registration($ab)
{
$bv = new Valitron\Validator($ab);
$fj = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$bv->rules($fj);
$bv->labels(['name' => '名称', 'gender' => '性别', 'birthdate' => '生日']);
if ($bv->validate()) {
return 0;
} else {
err($bv->errors());
}
}
}
}
namespace mid {
use FastRoute\Dispatcher;
use FastRoute\RouteParser\Std as StdParser;
class TwigMid
{
public function __invoke($fk, $fl, $fm)
{
log_time("Twig Begin");
$fl = $fm($fk, $fl);
$fn = uripath($fk);
debug(">>>>>> TwigMid START : {$fn}  <<<<<<");
if ($fo = $this->getRoutePath($fk)) {
$bo = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($bo->data);
}
$fp = rtrim($fo, '/');
if ($fp == '/' || !$fp) {
$fp = 'index';
}
$bn = $fp;
$ab = [];
if (isset($bo->data)) {
$ab = $bo->data;
if (isset($bo->data['tpl'])) {
$bn = $bo->data['tpl'];
}
}
$ab['uid'] = \ctx::uid();
$ab['isLogin'] = \ctx::user() ? true : false;
$ab['user'] = \ctx::user();
$ab['uri'] = \ctx::uri();
$ab['t'] = time();
$ab['domain'] = \cfg::get('wechat_callback_domain');
$ab['gdata'] = \ctx::global_view_data();
debug("<<<<<< TwigMid END : {$fn} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $bo->render($fl, tpl($bn), $ab);
} else {
return $fl;
}
}
public function getRoutePath($fk)
{
$fq = \ctx::router()->dispatch($fk);
if ($fq[0] === Dispatcher::FOUND) {
$aj = \ctx::router()->lookupRoute($fq[1]);
$fr = $aj->getPattern();
$fs = new StdParser();
$ft = $fs->parse($fr);
foreach ($ft as $fu) {
foreach ($fu as $dg) {
if (is_string($dg)) {
return $dg;
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
public function __invoke($fk, $fl, $fm)
{
log_time("AuthMid Begin");
$fn = uripath($fk);
debug(">>>>>> AuthMid START : {$fn}  <<<<<<");
\ctx::init($fk);
$this->check_auth($fk, $fl);
debug("<<<<<< AuthMid END : {$fn} >>>>>");
log_time("AuthMid END");
$fl = $fm($fk, $fl);
return $fl;
}
public function isAjax($bp = '')
{
if ($bp) {
if (startWith($bp, '/rest')) {
$this->isAjax = true;
}
}
return $this->isAjax;
}
public function check_auth($z, $bl)
{
list($fv, $ak, $fw) = $this->auth_cfg();
$fn = uripath($z);
$this->isAjax($fn);
if ($fn == '/') {
return true;
}
$fx = $this->check_list($fv, $fn);
if ($fx) {
$this->check_admin();
}
$fy = $this->check_list($ak, $fn);
if ($fy) {
$this->check_user();
}
$fz = $this->check_list($fw, $fn);
if (!$fz) {
$this->check_user();
}
info("check_auth: {$fn} admin:[{$fx}] user:[{$fy}] pub:[{$fz}]");
}
public function check_admin()
{
if (\ctx::isAdmin()) {
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
public function auth_error($gh = 1)
{
$gi = is_weixin();
$gj = isMobile();
$gk = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$gh}, is_weixin: {$gi} , is_mobile: {$gj}");
$gl = $_SERVER['REQUEST_URI'];
if ($gi) {
header("Location: {$gk}/auth/wechat?_r={$gl}");
exit;
}
if ($gj) {
header("Location: {$gk}/auth/openwechat?_r={$gl}");
exit;
}
if ($this->isAjax()) {
ret($gh, 'auth error');
} else {
header('Location: /?_r=' . $gl);
exit;
}
}
public function auth_cfg()
{
$gm = \cfg::get('auth');
return [$gm['admin'], $gm['user'], $gm['public']];
}
public function check_list($ai, $fn)
{
foreach ($ai as $bp) {
if (startWith($fn, $bp)) {
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
public function __invoke($fk, $fl, $fm)
{
$this->init($fk, $fl, $fm);
log_time("{$this->classname} Begin");
$this->path_info = uripath($fk);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($fk, $fl);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$fl = $fm($fk, $fl);
return $fl;
}
public function handelReq($z, $bl)
{
$bp = \cfg::get($this->classname, 'mid.yml');
if (is_array($bp)) {
$this->handlePathArray($bp, $z, $bl);
} else {
if (startWith($this->path_info, $bp)) {
$this->handlePath($z, $bl);
}
}
}
public function handlePathArray($gn, $z, $bl)
{
foreach ($gn as $bp => $go) {
if (startWith($this->path_info, $bp)) {
debug("{$this->path_info} match {$bp} {$go}");
$this->{$go}($z, $bl);
break;
}
}
}

public function handlePath($z, $bl)
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
public function __invoke($fk, $fl, $fm)
{
log_time("RestMid Begin");
$this->path_info = uripath($fk);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($fk)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($fk)) {
$this->apiDoc($fk);
} else {
$this->handelRest($fk);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$fl = $fm($fk, $fl);
return $fl;
}
public function isApiDoc($z)
{
return startWith($this->path_info, $this->rest_prefix . '/apidoc');
}
public function isRest($z)
{
return startWith($this->path_info, $this->rest_prefix);
}
public function handelRest($z)
{
$bp = str_replace($this->rest_prefix, '', $this->path_info);
$bq = explode('/', $bp);
$br = getArg($bq, 1, '');
$bs = getArg($bq, 2, '');
$go = $z->getMethod();
info(" method: {$go}, name: {$br}, id: {$bs}");
$gp = "handle{$go}";
$this->{$gp}($z, $br, $bs);
}
public function handleGET($z, $br, $bs)
{
if ($bs) {
rest::renderItem($br, $bs);
} else {
rest::renderList($br);
}
}
public function handlePOST($z, $br, $bs)
{
self::beforeData($br, 'post');
rest::renderPostData($br);
}
public function handlePUT($z, $br, $bs)
{
self::beforeData($br, 'put');
rest::renderPutData($br, $bs);
}
public function handleDELETE($z, $br, $bs)
{
rest::delete($z, $br, $bs);
}
public function handleOPTIONS($z, $br, $bs)
{
sendJson([]);
}
public function beforeData($br, $c)
{
$gq = \cfg::get('rest_maps', 'rest.yml');
if (isset($gq[$br])) {
$m = $gq[$br][$c];
if ($m) {
$gr = $m['xmap'];
if ($gr) {
$ab = \ctx::data();
foreach ($gr as $bu => $bv) {
unset($ab[$bv]);
}
\ctx::data($ab);
}
}
}
}
public function apiDoc($z)
{
$gs = rd::genApi();
echo $gs;
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
public static function whereStr($co, $br)
{
$v = '';
foreach ($co as $bu => $bv) {
$fr = '/(.*)\\{(.*)\\}/i';
$bt = preg_match($fr, $bu, $gt);
$gu = '=';
if ($gt) {
$gv = $gt[1];
$gu = $gt[2];
} else {
$gv = $bu;
}
if ($gw = \db::valid_table_col($br, $gv)) {
if ($gw == 2) {
$v .= " and t1.{$gv}{$gu}'{$bv}'";
} else {
$v .= " and t1.{$gv}{$gu}{$bv}";
}
} else {
}
info("[{$br}] [{$gv}] [{$gw}] {$v}");
}
return $v;
}
public static function getSqlFrom($br, $gx, $bc, $gy, $gz)
{
$hi = isset($_GET['tags']) ? 1 : 0;
$hj = isset($_GET['isar']) ? 1 : 0;
$hk = \cfg::rest('rest_xwh_tags_list');
if ($hk && in_array($br, $hk)) {
$hi = 0;
}
$hl = \ctx::isAdmin() && $hj ? "1=1" : "t1.uid={$bc}";
if ($hi) {
$hm = get('tags');
if ($hm && is_array($hm) && count($hm) == 1 && !$hm[0]) {
$hm = '';
}
$hn = '';
$ho = 'not in';
if ($hm) {
if (is_string($hm)) {
$hm = [$hm];
}
$hp = implode("','", $hm);
$hn = "and `name` in ('{$hp}')";
$ho = 'in';
$hq = " from {$br} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$gx}\n                               where {$hl} and t._st=1  and t.tagid {$ho}\n                               (select id from tags where type='{$br}' {$hn} )\n                               {$gz}";
} else {
$hq = " from {$br} t1\n                              {$gx}\n                              where {$hl} and t1.id not in\n                              (select oid from tag_items where type='{$br}')\n                              {$gz}";
}
} else {
$hr = $hl;
if (!\ctx::isAdmin()) {
if ($br == \ctx::user_tbl()) {
$hr = "t1.id={$bc}";
}
}
$hq = "from {$br} t1 {$gx} where {$hr} {$gy} {$gz}";
}
return $hq;
}
public static function getSql($br)
{
$bc = \ctx::uid();
$hs = get('sort', '_intm');
$ht = get('asc', -1);
if (!\db::valid_table_col($br, $hs)) {
$hs = '_intm';
}
$ht = $ht > 0 ? 'asc' : 'desc';
$gz = " order by t1.{$hs} {$ht}";
$hu = gets();
$hu = un_select_keys(['sort', 'asc'], $hu);
$hv = get('_st', 1);
$co = dissoc($hu, ['token', '_st']);
if ($hv != 'all') {
$co['_st'] = $hv;
}
$gy = self::whereStr($co, $br);
$hw = get('search', '');
$hx = get('search-key', '');
if ($hw && $hx) {
$gy .= " and {$hx} like '%{$hw}%'";
}
$hy = \ctx::rest_select_add();
$gx = \ctx::rest_join_add();
$hq = self::getSqlFrom($br, $gx, $bc, $gy, $gz);
$cf = "select t1.* {$hy} {$hq}";
$hz = "select count(*) cnt {$hq}";
$ag = \ctx::offset();
$af = \ctx::pagesize();
$cf .= " limit {$ag},{$af}";
return [$cf, $hz];
}
public static function getResName($br)
{
$ij = get('res_id_key', '');
if ($ij) {
$ik = get($ij);
$br .= '_' . $ik;
}
return $br;
}
public static function getList($br, $es = array())
{
$bc = \ctx::uid();
list($cf, $hz) = self::getSql($br);
$cl = \db::query($cf);
$ap = (int) \db::queryOne($hz);
$il = \cfg::rest('rest_join_tags_list');
if ($il && in_array($br, $il)) {
$im = getKeyValues($cl, 'id');
$hm = tag::getTagsByOids($bc, $im, $br);
info("get tags ok: {$bc} {$br} " . json_encode($im));
foreach ($cl as $bu => $cx) {
if (isset($hm[$cx['id']])) {
$in = $hm[$cx['id']];
$cl[$bu]['tags'] = getKeyValues($in, 'name');
}
}
info('set tags ok');
}
if (isset($es['join_cols'])) {
foreach ($es['join_cols'] as $io => $ip) {
$iq = getArg($ip, 'jtype', '1-1');
$ir = getArg($ip, 'jkeys', []);
$is = getArg($ip, 'jwhe', []);
if (is_string($ip['on'])) {
$it = 'id';
$iu = $ip['on'];
} else {
if (is_array($ip['on'])) {
$iv = array_keys($ip['on']);
$it = $iv[0];
$iu = $ip['on'][$it];
}
}
$im = getKeyValues($cl, $it);
$is[$iu] = $im;
$iw = \db::all($io, ['AND' => $is]);
foreach ($iw as $k => $ix) {
foreach ($cl as $bu => &$cx) {
if (isset($cx[$it]) && isset($ix[$iu]) && $cx[$it] == $ix[$iu]) {
if ($iq == '1-1') {
foreach ($ir as $iy => $iz) {
$cx[$iz] = $ix[$iy];
}
}
$iy = isset($ip['jkey']) ? $ip['jkey'] : $io;
if ($iq == '1-n') {
$cx[$iy][] = $ix[$iy];
}
if ($iq == '1-n-o') {
$cx[$iy][] = $ix;
}
if ($iq == '1-1-o') {
$cx[$iy] = $ix;
}
}
}
}
}
}
$jk = self::getResName($br);
return ['data' => $cl, 'res-name' => $jk, 'count' => $ap];
}
public static function renderList($br)
{
ret(self::getList($br));
}
public static function getItem($br, $bs)
{
$bc = \ctx::uid();
info("---GET---: {$br}/{$bs}");
$jk = "{$br}-{$bs}";
if ($br == 'colls') {
$dg = \db::row($br, ["{$br}.id" => $bs], ["{$br}.id", "{$br}.title", "{$br}.from_url", "{$br}._intm", "{$br}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($br == 'feeds') {
$c = get('type');
$jl = get('rid');
$dg = \db::row($br, ['AND' => ['uid' => $bc, 'rid' => $bs, 'type' => $c]]);
if (!$dg) {
$dg = ['rid' => $bs, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$jk = "{$jk}-{$c}-{$bs}";
} else {
$dg = \db::row($br, ['id' => $bs]);
}
}
if (\ctx::rest_extra_data()) {
$dg = array_merge($dg, \ctx::rest_extra_data());
}
return ['data' => $dg, 'res-name' => $jk, 'count' => 1];
}
public static function renderItem($br, $bs)
{
ret(self::getItem($br, $bs));
}
public static function postData($br)
{
$ab = \db::tbl_data($br, \ctx::data());
$bc = \ctx::uid();
$hm = [];
if ($br == 'tags') {
$hm = tag::getTagByName($bc, $ab['name'], $ab['type']);
}
if ($hm && $br == 'tags') {
$ab = $hm[0];
} else {
info("---POST---: {$br} " . json_encode($ab));
unset($ab['token']);
$ab['_intm'] = date('Y-m-d H:i:s');
if (!isset($ab['uid'])) {
$ab['uid'] = $bc;
}
$ab = \db::tbl_data($br, $ab);
\vld::test($br, $ab);
$ab = \db::save($br, $ab);
}
return $ab;
}
public static function renderPostData($br)
{
$ab = self::postData($br);
ret($ab);
}
public static function putData($br, $bs)
{
if ($bs == 0 || $bs == '' || trim($bs) == '') {
info(" PUT ID IS EMPTY !!!");
ret();
}
$bc = \ctx::uid();
$ab = \ctx::data();
unset($ab['token']);
unset($ab['uniqid']);
self::checkOwner($br, $bs, $bc);
if (isset($ab['inc'])) {
$jm = $ab['inc'];
unset($ab['inc']);
\db::exec("UPDATE {$br} SET {$jm} = {$jm} + 1 WHERE id={$bs}");
}
if (isset($ab['dec'])) {
$jm = $ab['dec'];
unset($ab['dec']);
\db::exec("UPDATE {$br} SET {$jm} = {$jm} - 1 WHERE id={$bs}");
}
if (isset($ab['tags'])) {
info("up tags");
tag::delTagByOid($bc, $bs, $br);
$hm = $ab['tags'];
foreach ($hm as $jn) {
$jo = tag::getTagByName($bc, $jn, $br);
info($jo);
if ($jo) {
$jp = $jo[0]['id'];
tag::saveTagItems($bc, $jp, $bs, $br);
}
}
}
info("---PUT---: {$br}/{$bs} " . json_encode($ab));
$ab = \db::tbl_data($br, \ctx::data());
$ab['id'] = $bs;
\db::save($br, $ab);
return $ab;
}
public static function renderPutData($br, $bs)
{
$ab = self::putData($br, $bs);
ret($ab);
}
public static function delete($z, $br, $bs)
{
$bc = \ctx::uid();
self::checkOwner($br, $bs, $bc);
\db::save($br, ['_st' => 0, 'id' => $bs]);
ret([]);
}
public static function checkOwner($br, $bs, $bc)
{
$co = ['AND' => ['id' => $bs], 'LIMIT' => 1];
$cl = \db::obj()->select($br, '*', $co);
if ($cl) {
$dg = $cl[0];
} else {
$dg = null;
}
if ($dg) {
if (array_key_exists('uid', $dg)) {
$jq = $dg['uid'];
if ($br == \ctx::user_tbl()) {
$jq = $dg['id'];
}
if ($jq != $bc && (!\ctx::isAdmin() || !\ctx::isAdminRest())) {
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
public static function getTagByName($bc, $jn, $c)
{
$hm = \db::all(self::$tbl_name, ['AND' => ['uid' => $bc, 'name' => $jn, 'type' => $c, '_st' => 1]]);
return $hm;
}
public static function delTagByOid($bc, $jr, $js)
{
info("del tag: {$bc}, {$jr}, {$js}");
$v = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $bc, 'oid' => $jr, 'type' => $js]]);
info($v);
}
public static function saveTagItems($bc, $jt, $jr, $js)
{
\db::save('tag_items', ['tagid' => $jt, 'uid' => $bc, 'oid' => $jr, 'type' => $js, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($bc, $c)
{
$hm = \db::all(self::$tbl_name, ['AND' => ['uid' => $bc, 'type' => $c, '_st' => 1]]);
return $hm;
}
public static function getTagsByOid($bc, $jr, $c)
{
$cf = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$jr} and t2.type='{$c}' and t2._st=1";
$cl = \db::query($cf);
return getKeyValues($cl, 'name');
}
public static function getTagsByOids($bc, $ju, $c)
{
if (is_array($ju)) {
$ju = implode(',', $ju);
}
$cf = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$ju}) and t2.type='{$c}' and t2._st=1";
$cl = \db::query($cf);
$ab = groupArray($cl, 'oid');
return $ab;
}
public static function countByTag($bc, $jn, $c)
{
$cf = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$jn}' and t1.type='{$c}' and t1.uid={$bc}";
$cl = \db::query($cf);
return [$cl[0]['cnt'], $cl[0]['id']];
}
public static function saveTag($bc, $jn, $c)
{
$ab = ['uid' => $bc, 'name' => $jn, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$ab = \db::save('tags', $ab);
return $ab;
}
public static function countTags($bc, $jv, $br)
{
foreach ($jv as $jn) {
list($jw, $bs) = self::countByTag($bc, $jn, $br);
echo "{$jn} {$jw} {$bs} <br>";
\db::update('tags', ['count' => $jw], ['id' => $bs]);
}
}
public static function saveRepoTags($bc, $jx)
{
$br = 'stars';
echo count($jx) . "<br>";
$jv = [];
foreach ($jx as $jy) {
$jz = $jy['repoId'];
$hm = isset($jy['tags']) ? $jy['tags'] : [];
if ($hm) {
foreach ($hm as $jn) {
if (!in_array($jn, $jv)) {
$jv[] = $jn;
}
$hm = self::getTagByName($bc, $jn, $br);
if (!$hm) {
$jo = self::saveTag($bc, $jn, $br);
} else {
$jo = $hm[0];
}
$jt = $jo['id'];
$kl = getStarByRepoId($bc, $jz);
if ($kl) {
$jr = $kl[0]['id'];
$km = self::getTagsByOid($bc, $jr, $br);
if ($jo && !in_array($jn, $km)) {
self::saveTagItems($bc, $jt, $jr, $br);
}
} else {
echo "-------- star for {$jz} not found <br>";
}
}
} else {
}
}
self::countTags($bc, $jv, $br);
}
public static function getTagItem($kn, $bc, $ko, $de, $kp)
{
$cf = "select * from {$ko} where {$de}={$kp} and uid={$bc}";
return $kn->query($cf)->fetchAll();
}
public static function saveItemTags($kn, $bc, $br, $kq, $de = 'id')
{
echo count($kq) . "<br>";
$jv = [];
foreach ($kq as $kr) {
$kp = $kr[$de];
$hm = isset($kr['tags']) ? $kr['tags'] : [];
if ($hm) {
foreach ($hm as $jn) {
if (!in_array($jn, $jv)) {
$jv[] = $jn;
}
$hm = getTagByName($kn, $bc, $jn, $br);
if (!$hm) {
$jo = saveTag($kn, $bc, $jn, $br);
} else {
$jo = $hm[0];
}
$jt = $jo['id'];
$kl = getTagItem($kn, $bc, $br, $de, $kp);
if ($kl) {
$jr = $kl[0]['id'];
$km = getTagsByOid($kn, $bc, $jr, $br);
if ($jo && !in_array($jn, $km)) {
saveTagItems($kn, $bc, $jt, $jr, $br);
}
} else {
echo "-------- star for {$kp} not found <br>";
}
}
} else {
}
}
countTags($kn, $bc, $jv, $br);
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
public function __construct($ks = '')
{
if ($ks) {
$this->service = $ks;
$es = self::$_services[$this->service];
$kt = $es['url'];
debug("init client: {$kt}");
$this->client = new Client(['base_uri' => $kt, 'timeout' => 12.0]);
}
}
public static function add($es = array())
{
if ($es) {
$br = $es['name'];
if (!isset(self::$_services[$br])) {
self::$_services[$br] = $es;
}
}
}
public static function init()
{
$ku = \cfg::get('service_list', 'service');
foreach ($ku as $m) {
self::add($m);
}
}
public function getRest($ks, $x = '/rest')
{
return $this->get($ks, $x . '/');
}
public function get($ks, $x = '')
{
if (isset(self::$_services[$ks])) {
if (!isset(self::$_ins[$ks])) {
self::$_ins[$ks] = new Service($ks);
}
}
if (isset(self::$_ins[$ks])) {
$kv = self::$_ins[$ks];
if ($x) {
$kv->setPrefix($x);
}
return $kv;
} else {
return null;
}
}
public function setPrefix($x)
{
$this->prefix = $x;
}
public function __call($kw, $kx)
{
$es = self::$_services[$this->service];
$kt = $es['url'];
$bh = $es['appid'];
$be = $es['appkey'];
$ab = $kx[0];
$ab = array_merge($ab, $_GET);
$ab['appid'] = $bh;
$ab['date'] = date("Y-m-d H:i:s");
$ab['sign'] = gen_sign($ab, $be);
$go = getArg($kx, 1, 'GET');
$ky = getArg($kx, 2, '');
$kw = $this->prefix . $kw . $ky;
debug("api_url: {$bh} {$be} {$kt}");
debug("api_name: {$kw} {$go}");
debug("data: " . json_encode($ab));
try {
$this->resp = $this->client->request($go, $kw, ['form_params' => $ab]);
} catch (Exception $e) {
}
return $this;
}
public function json()
{
$bt = $this->body();
$ab = json_decode($bt, true);
return $ab;
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
public function __get($kz)
{
$go = 'get' . ucfirst($kz);
if (method_exists($this, $go)) {
$lm = new ReflectionMethod($this, $go);
if (!$lm->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $kz)) {
return $this->{$kz};
}
}
public function __set($kz, $l)
{
$go = 'set' . ucfirst($kz);
if (method_exists($this, $go)) {
$lm = new ReflectionMethod($this, $go);
if (!$lm->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $kz)) {
$this->{$kz} = $l;
}
}
}
}
namespace {
error_reporting(E_ALL);
function cache_shutdown_error()
{
$ln = error_get_last();
if ($ln && in_array($ln['type'], array(1, 4, 16, 64, 256, 4096, E_ALL))) {
echo '<font color=red>你的代码出错了：</font></br>';
echo '致命错误:' . $ln['message'] . '</br>';
echo '文件:' . $ln['file'] . '</br>';
echo '在第' . $ln['line'] . '行</br>';
}
}
register_shutdown_function("cache_shutdown_error");
function getCaller($lo = NULL)
{
$lp = debug_backtrace();
$lq = $lp[2];
if (isset($lo)) {
return $lq[$lo];
} else {
return $lq;
}
}
function getCallerStr($lr = 4)
{
$lp = debug_backtrace();
$lq = $lp[2];
$ls = $lp[1];
$lt = $lq['function'];
$lu = isset($lq['class']) ? $lq['class'] : '';
$lv = $ls['file'];
$lw = $ls['line'];
if ($lr == 4) {
$bt = "{$lu} {$lt} {$lv} {$lw}";
} elseif ($lr == 3) {
$bt = "{$lu} {$lt} {$lw}";
} else {
$bt = "{$lu} {$lw}";
}
return $bt;
}
function wlog($bp, $lx, $ly)
{
if (is_dir($bp)) {
$lz = date('Y-m-d', time());
$ly .= "\n";
file_put_contents($bp . "/{$lx}-{$lz}.log", $ly, FILE_APPEND);
}
}
function folder_exist($mn)
{
$bp = realpath($mn);
return ($bp !== false and is_dir($bp)) ? $bp : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($ab, $mo)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$mp = $m['symmetric_key'];
$mq = $m['hmac_key'];
$mr = new AES_SHA($mp, $mq);
return $mr->encrypt(serialize($ab), $mo);
}
function decrypt($ab)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$mp = $m['symmetric_key'];
$mq = $m['hmac_key'];
$mr = new AES_SHA($mp, $mq);
return unserialize($mr->decrypt($ab));
}
function encrypt_cookie($ms)
{
return encrypt($ms->getData(), $ms->getExpiration());
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($dr, $mt = 'DECODE', $k = '', $mu = 0)
{
$mv = 4;
$k = md5($k ? $k : UC_KEY);
$mw = md5(substr($k, 0, 16));
$mx = md5(substr($k, 16, 16));
$my = $mv ? $mt == 'DECODE' ? substr($dr, 0, $mv) : substr(md5(microtime()), -$mv) : '';
$mz = $mw . md5($mw . $my);
$no = strlen($mz);
$dr = $mt == 'DECODE' ? base64_decode(substr($dr, $mv)) : sprintf('%010d', $mu ? $mu + time() : 0) . substr(md5($dr . $mx), 0, 16) . $dr;
$np = strlen($dr);
$ds = '';
$nq = range(0, 255);
$nr = array();
for ($dy = 0; $dy <= 255; $dy++) {
$nr[$dy] = ord($mz[$dy % $no]);
}
for ($ns = $dy = 0; $dy < 256; $dy++) {
$ns = ($ns + $nq[$dy] + $nr[$dy]) % 256;
$du = $nq[$dy];
$nq[$dy] = $nq[$ns];
$nq[$ns] = $du;
}
for ($nt = $ns = $dy = 0; $dy < $np; $dy++) {
$nt = ($nt + 1) % 256;
$ns = ($ns + $nq[$nt]) % 256;
$du = $nq[$nt];
$nq[$nt] = $nq[$ns];
$nq[$ns] = $du;
$ds .= chr(ord($dr[$dy]) ^ $nq[($nq[$nt] + $nq[$ns]) % 256]);
}
if ($mt == 'DECODE') {
if ((substr($ds, 0, 10) == 0 || substr($ds, 0, 10) - time() > 0) && substr($ds, 10, 16) == substr(md5(substr($ds, 26) . $mx), 0, 16)) {
return substr($ds, 26);
} else {
return '';
}
} else {
return $my . str_replace('=', '', base64_encode($ds));
}
}

function object2array(&$nu)
{
$nu = json_decode(json_encode($nu), true);
return $nu;
}
function getKeyValues($ab, $k, $cg = null)
{
if (!$cg) {
$cg = function ($bv) {
return $bv;
};
}
$nv = array();
if ($ab && is_array($ab)) {
foreach ($ab as $dg) {
if (isset($dg[$k]) && $dg[$k]) {
$u = $dg[$k];
if ($cg) {
$u = $cg($u);
}
$nv[] = $u;
}
}
}
return array_unique($nv);
}
if (!function_exists('indexArray')) {
function indexArray($ab, $k, $er = null)
{
$nv = array();
if ($ab && is_array($ab)) {
foreach ($ab as $dg) {
if (!isset($dg[$k]) || !$dg[$k] || !is_scalar($dg[$k])) {
continue;
}
if (!$er) {
$nv[$dg[$k]] = $dg;
} else {
if (is_string($er)) {
$nv[$dg[$k]] = $dg[$er];
} else {
if (is_array($er)) {
$nw = [];
foreach ($er as $bu => $bv) {
$nw[$bv] = $dg[$bv];
}
$nv[$dg[$k]] = $dg[$er];
}
}
}
}
}
return $nv;
}
}
if (!function_exists('groupArray')) {
function groupArray($nx, $k)
{
if (!is_array($nx) || !$nx) {
return array();
}
$ab = array();
foreach ($nx as $dg) {
if (isset($dg[$k]) && $dg[$k]) {
$ab[$dg[$k]][] = $dg;
}
}
return $ab;
}
}
function select_keys($cz, $ab)
{
$v = [];
foreach ($cz as $k) {
if (isset($ab[$k])) {
$v[$k] = $ab[$k];
} else {
$v[$k] = '';
}
}
return $v;
}
function un_select_keys($cz, $ab)
{
$v = [];
foreach ($ab as $bu => $dg) {
if (!in_array($bu, $cz)) {
$v[$bu] = $dg;
}
}
return $v;
}
function copyKey($ab, $ny, $nz)
{
foreach ($ab as &$dg) {
$dg[$nz] = $dg[$ny];
}
return $ab;
}
function addKey($ab, $k, $u)
{
foreach ($ab as &$dg) {
$dg[$k] = $u;
}
return $ab;
}
function dissoc($nx, $cz)
{
if (is_array($cz)) {
foreach ($cz as $k) {
unset($nx[$k]);
}
} else {
unset($nx[$cz]);
}
return $nx;
}
function insertAt($op, $oq, $l)
{
array_splice($op, $oq, 0, [$l]);
return $op;
}
function getArg($or, $os, $ot = '')
{
if (isset($or[$os])) {
return $or[$os];
} else {
return $ot;
}
}
function permu($au, $cu = ',')
{
$ai = [];
if (is_string($au)) {
$ou = str_split($au);
} else {
$ou = $au;
}
sort($ou);
$ov = count($ou) - 1;
$ow = $ov;
$ap = 1;
$dg = implode($cu, $ou);
$ai[] = $dg;
while (true) {
$ox = $ow--;
if ($ou[$ow] < $ou[$ox]) {
$oy = $ov;
while ($ou[$ow] > $ou[$oy]) {
$oy--;
}

list($ou[$ow], $ou[$oy]) = array($ou[$oy], $ou[$ow]);

for ($dy = $ov; $dy > $ox; $dy--, $ox++) {
list($ou[$dy], $ou[$ox]) = array($ou[$ox], $ou[$dy]);
}
$dg = implode($cu, $ou);
$ai[] = $dg;
$ow = $ov;
$ap++;
}
if ($ow == 0) {
break;
}
}
return $ai;
}
function combin($nv, $oz, $pq = ',')
{
$ds = array();
if ($oz == 1) {
return $nv;
}
if ($oz == count($nv)) {
$ds[] = implode($pq, $nv);
return $ds;
}
$pr = $nv[0];
unset($nv[0]);
$nv = array_values($nv);
$ps = combin($nv, $oz - 1, $pq);
foreach ($ps as $pt) {
$pt = $pr . $pq . $pt;
$ds[] = $pt;
}
unset($ps);
$pu = combin($nv, $oz, $pq);
foreach ($pu as $pt) {
$ds[] = $pt;
}
unset($pu);
return $ds;
}
function getExcelCol($ch)
{
$nv = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($ch == 0) {
return '';
}
return getExcelCol((int) (($ch - 1) / 26)) . $nv[$ch % 26];
}
function getExcelPos($cx, $ch)
{
return getExcelCol($ch) . $cx;
}
function sendJSON($ab)
{
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Accept,X-Requested-With");
header("Content-type: application/json");
header("Access-Control-Allow-Credentials:true");
log_time("sendJSON Total", 'begin');
if (is_array($ab)) {
echo json_encode($ab);
} else {
echo $ab;
}
exit;
}
function succ($nv = array(), $pv = 'succ', $pw = 1)
{
$ab = $nv;
$px = 0;
$py = 1;
$ap = 0;
$v = array($pv => $pw, 'errormsg' => '', 'errorfield' => '');
if (isset($nv['data'])) {
$ab = $nv['data'];
}
if (isset($nv['total_page'])) {
$v['total_page'] = $nv['total_page'];
}
if (isset($nv['cur_page'])) {
$v['cur_page'] = $nv['cur_page'];
}
if (isset($nv['count'])) {
$v['count'] = $nv['count'];
}
if (isset($nv['res-name'])) {
$v['res-name'] = $nv['res-name'];
}
$v['data'] = $ab;
sendJSON($v);
}
function fail($nv = array(), $pv = 'succ', $pz = 0)
{
$k = $ly = '';
if (count($nv) > 0) {
$cz = array_keys($nv);
$k = $cz[0];
$ly = $nv[$k][0];
}
$v = array($pv => $pz, 'errormsg' => $ly, 'errorfield' => $k);
sendJSON($v);
}
function code($nv = array(), $ev = 0)
{
if (is_string($ev)) {
}
if ($ev == 0) {
succ($nv, 'code', 0);
} else {
fail($nv, 'code', $ev);
}
}
function ret($nv = array(), $ev = 0, $jm = '')
{
$nt = $nv;
$qr = $ev;
if (is_numeric($nv) || is_string($nv)) {
$qr = $nv;
$nt = array();
if (is_array($ev)) {
$nt = $ev;
} else {
$ev = $ev === 0 ? '' : $ev;
$nt = array($jm => array($ev));
}
}
code($nt, $qr);
}
function err($qs)
{
code($qs, 1);
}
function downloadExcel($qt, $dv)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $dv . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$qt->save('php://output');
}
function dd($ab)
{
dump($ab);
exit;
}
function cacert_file()
{
return ROOT_PATH . "/fn/cacert.pem";
}
function curl($ep, $qu = 10, $qv = 30, $qw = '')
{
$qx = curl_init($ep);
curl_setopt($qx, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($qx, CURLOPT_CONNECTTIMEOUT, $qu);
curl_setopt($qx, CURLOPT_HEADER, 0);
curl_setopt($qx, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($qx, CURLOPT_TIMEOUT, $qv);
if (file_exists(cacert_file())) {
curl_setopt($qx, CURLOPT_CAINFO, cacert_file());
}
if ($qw) {
if (is_array($qw)) {
$qw = http_build_query($qw);
}
curl_setopt($qx, CURLOPT_POST, 1);
curl_setopt($qx, CURLOPT_POSTFIELDS, $qw);
}
$ds = curl_exec($qx);
if (curl_errno($qx)) {
return '';
}
curl_close($qx);
return $ds;
}
function curl_header($ep, $qu = 10, $qv = 30)
{
$qx = curl_init($ep);
curl_setopt($qx, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($qx, CURLOPT_CONNECTTIMEOUT, $qu);
curl_setopt($qx, CURLOPT_HEADER, 1);
curl_setopt($qx, CURLOPT_NOBODY, 1);
curl_setopt($qx, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($qx, CURLOPT_TIMEOUT, $qv);
if (file_exists(cacert_file())) {
curl_setopt($qx, CURLOPT_CAINFO, cacert_file());
}
$ds = curl_exec($qx);
if (curl_errno($qx)) {
return '';
}
return $ds;
}

function startWith($bt, $pt)
{
return strpos($bt, $pt) === 0;
}
function endWith($qy, $qz)
{
$rs = strlen($qz);
if ($rs == 0) {
return true;
}
return substr($qy, -$rs) === $qz;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $ab, $rt = false, $jm = '')
{
$nx = getKeyValues($ab, $k);
if (!$nx) {
return '';
}
if ($rt) {
foreach ($nx as $bu => $bv) {
$nx[$bu] = "'{$bv}'";
}
}
$bt = implode(',', $nx);
if ($jm) {
$k = $jm;
}
return " {$k} in ({$bt})";
}
function get_top_domain($ep)
{
$fr = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($fr, $ep, $ru);
if (count($ru) > 0) {
return $ru[0];
} else {
$rv = parse_url($ep);
$rw = $rv["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($rw))), $rw)) {
return $rw;
} else {
$nv = explode(".", $rw);
$ap = count($nv);
$rx = array("com", "net", "org", "3322");
if (in_array($nv[$ap - 2], $rx)) {
$gk = $nv[$ap - 3] . "." . $nv[$ap - 2] . "." . $nv[$ap - 1];
} else {
$gk = $nv[$ap - 2] . "." . $nv[$ap - 1];
}
return $gk;
}
}
}
function genID($ls)
{
list($ry, $rz) = explode(" ", microtime());
$st = rand(0, 100);
return $ls . $rz . substr($ry, 2, 6);
}
function cguid($su = false)
{
mt_srand((double) microtime() * 10000);
$sv = md5(uniqid(rand(), true));
return $su ? strtoupper($sv) : $sv;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$sw = cguid();
$sx = chr(45);
$sy = chr(123) . substr($sw, 0, 8) . $sx . substr($sw, 8, 4) . $sx . substr($sw, 12, 4) . $sx . substr($sw, 16, 4) . $sx . substr($sw, 20, 12) . chr(125);
return $sy;
}
}
function randstr($jw = 6)
{
return substr(md5(rand()), 0, $jw);
}
function hashsalt($em, $sz = '')
{
$sz = $sz ? $sz : randstr(10);
$tu = md5(md5($em) . $sz);
return [$tu, $sz];
}
function gen_letters($jw = 26)
{
$pt = '';
for ($dy = 65; $dy < 65 + $jw; $dy++) {
$pt .= strtolower(chr($dy));
}
return $pt;
}
function gen_sign($bd, $aw = null)
{
if ($aw == null) {
return false;
}
return strtoupper(md5(strtoupper(md5(assemble($bd))) . $aw));
}
function assemble($bd)
{
if (!is_array($bd)) {
return null;
}
ksort($bd, SORT_STRING);
$tv = '';
foreach ($bd as $k => $u) {
$tv .= $k . (is_array($u) ? assemble($u) : $u);
}
return $tv;
}
function check_sign($bd, $aw = null)
{
$tv = getArg($bd, 'sign');
$tw = getArg($bd, 'date');
$tx = strtotime($tw);
$ty = time();
$tz = $ty - $tx;
debug("check_sign : {$ty} - {$tx} = {$tz}");
if (!$tw || $ty - $tx > 60) {
debug("check_sign fail : {$tw} delta > 60");
return false;
}
unset($bd['sign']);
$uv = gen_sign($bd, $aw);
debug("{$tv} -- {$uv}");
return $tv == $uv;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$uw = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$uw = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$uw = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$uw = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$uw = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$uw = getenv("REMOTE_ADDR");
} else {
$uw = "Unknown";
}
}
}
}
}
}
return $uw;
}
function getRIP()
{
$uw = $_SERVER["REMOTE_ADDR"];
return $uw;
}
function env($k = 'DEV_MODE', $ot = '')
{
$l = getenv($k);
return $l ? $l : $ot;
}
function vpath()
{
$bp = getenv("VENDER_PATH");
if ($bp) {
return $bp;
} else {
return ROOT_PATH;
}
}
function config($k = '')
{
return cfg::config($k);
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
$ux = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $ux) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
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
function cache($k, $cg = null, $rz = 10, $uy = 0)
{
$uz = new FilesystemCache();
if ($cg) {
if (is_callable($cg)) {
if ($uy || !$uz->has($k)) {
$ab = $cg();
debug("--------- fn: no cache for [{$k}] ----------");
$uz->set($k, $ab, $rz);
} else {
$ab = $uz->get($k);
debug("======= fn: data from cache [{$k}] ========");
}
} else {
debug("--------- set cache for [{$k}] ---------- : " . json_encode($cg));
$uz->set($k, $cg, $rz);
$ab = $cg;
}
} else {
debug("--------- get cache for [{$k}] ---------- ");
$ab = $uz->get($k);
}
return $ab;
}
function cache_del($k)
{
$uz = new FilesystemCache();
$uz->delete($k);
debug("!!!!!!--------- delete cache for [{$k}] ----------!!!!!!");
}
function cache_clear()
{
$uz = new FilesystemCache();
$uz->clear();
debug("!!!!!!--------- clear all cache ----------!!!!!!");
}
function baseModel($vw)
{
return <<<EOF


class {$vw}
{
    protected \$id;
    protected \$intm;
    protected \$st;


EOF;
    
}
function baseArray($vw, $dj)
{
return array("{$vw}" => array('type' => 'entity', 'table' => $dj, 'id' => array('id' => array('type' => 'integer', 'id' => true, 'generator' => array('strategy' => 'IDENTITY'))), 'fields' => array('intm' => array('type' => 'datetime', 'column' => '_intm'), 'st' => array('type' => 'integer', 'column' => '_st', 'options' => array('default' => 1)))));
}
function genObj($vw)
{
$vx = array_merge(\db::col_array('sys_objects'), ['sys_object_item.name(itemname)', 'sys_object_item.colname', 'sys_object_item.type', 'sys_object_item.length', 'sys_object_item.default', 'sys_object_item.comment']);
$cu = ['[>]sys_object_item' => ['id' => 'oid']];
$dh = ['AND' => ['sys_objects.name' => $vw], 'ORDER' => ['sys_objects.id' => 'DESC']];
$cl = \db::all('sys_objects', $dh, $vx, $cu);
if ($cl) {
$dj = $cl[0]['table'];
$ab = baseArray($vw, $dj);
$vy = baseModel($vw);
foreach ($cl as $cx) {
if (!$cx['itemname']) {
continue;
}
$vz = $cx['colname'] ? $cx['colname'] : $cx['itemname'];
$jm = ['type' => "{$cx['type']}", 'column' => "{$vz}", 'options' => array('default' => "{$cx['default']}", 'comment' => "{$cx['comment']}")];
$ab[$vw]['fields'][$cx['itemname']] = $jm;
$vy .= "    protected \${$cx['itemname']}; \n";
}
$vy .= '}';
}
return [$ab, $vy];
}
function writeObjFile($vw)
{
list($ab, $vy) = genObj($vw);
$wx = \Symfony\Component\Yaml\Yaml::dump($ab);
$wy = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$wz = $wy . '/src/objs';
if (!is_dir($wz)) {
mkdir($wz);
}
file_put_contents("{$wz}/{$vw}.php", $vy);
file_put_contents("{$wz}/{$vw}.dcm.yml", $wx);
}
function sync_to_db()
{
$wy = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$xy = "cd {$wy} && sh ./run.sh";
exec($xy, $nx);
foreach ($nx as $dg) {
echo \SqlFormatter::format($dg);
}
}
function fixfn($bw)
{
foreach ($bw as $bx) {
if (!function_exists($bx)) {
eval("function {$bx}(){}");
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
function ms($br)
{
return \ctx::container()->ms->get($br);
}
function rms($br, $x = 'rest')
{
return \ctx::container()->ms->getRest($br, $x);
}
use db\Rest as rest;
function getMetaData($vw, $xz = array())
{
ctx::pagesize(50);
$yz = db::all('sys_objects');
$abc = array_filter($yz, function ($bv) use($vw) {
return $bv['name'] == $vw;
});
$abc = array_shift($abc);
$abd = $abc['id'];
ctx::gets('oid', $abd);
$abe = rest::getList('sys_object_item');
$abf = $abe['data'];
$abg = ['Id'];
$abh = [0.1];
$ct = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($abf as $dg) {
$br = $dg['name'];
$vz = $dg['colname'] ? $dg['colname'] : $br;
$c = $dg['type'];
$ot = $dg['default'];
$abi = $dg['col_width'];
$abj = $dg['readonly'] ? ture : false;
$abk = $dg['is_meta'];
if ($abk) {
$abg[] = $br;
$abh[] = (double) $abi;
if (in_array($vz, array_keys($xz))) {
$ct[] = $xz[$vz];
} else {
$ct[] = ['data' => $vz, 'renderer' => 'html', 'readOnly' => $abj];
}
}
}
$abg[] = "InTm";
$abg[] = "St";
$abh[] = 60;
$abh[] = 10;
$ct[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$ct[] = ['data' => "_st", 'renderer' => "html"];
$abl = ['objname' => $vw];
return [$abl, $abg, $abh, $ct];
}
function auto_reg_user($abm = 'username', $abn = 'password', $cd = 'user', $abo = 0)
{
$abp = randstr(10);
$em = randstr(6);
$ab = ["{$abm}" => $abp, "{$abn}" => $em, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($abo) {
list($em, $sz) = hashsalt($em);
$ab[$abn] = $em;
$ab['salt'] = $sz;
} else {
$ab[$abn] = md5($em);
}
return db::save($cd, $ab);
}
function refresh_token($cd, $bc, $gk = '')
{
$abq = cguid();
$ab = ['id' => $bc, 'token' => $abq];
$ak = db::save($cd, $ab);
if ($gk) {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/', $gk);
} else {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/');
}
return $ak;
}
function user_login($app, $abm = 'username', $abn = 'password', $cd = 'user', $abo = 0)
{
$ab = ctx::data();
$ab = select_keys([$abm, $abn], $ab);
$abp = $ab[$abm];
$em = $ab[$abn];
if (!$abp || !$em) {
return NULL;
}
$ak = \db::row($cd, ["{$abm}" => $abp]);
if ($ak) {
if ($abo) {
$sz = $ak['salt'];
list($em, $sz) = hashsalt($em, $sz);
} else {
$em = md5($em);
}
if ($em == $ak[$abn]) {
refresh_token($cd, $ak['id']);
return $ak;
}
}
return NULL;
}
function uc_auto_reg_user($el, $abr)
{
$v = \uc::find_user(['username' => $el]);
if ($v['code'] != 0) {
$v = uc::reg_user($el, $abr);
} else {
$v = ['code' => 1, 'data' => []];
}
return $v;
}
function uc_login_data($bg)
{
$ay = uc::user_info($bg);
$ay = $ay['data'];
$fi = [];
$abs = uc::user_role($bg, 1);
$abt = [];
if ($abs['code'] == 0) {
$abt = $abs['data']['roles'];
if ($abt) {
foreach ($abt as $k => $fg) {
$fi[] = $fg['name'];
}
}
}
$ay['roles'] = $fi;
return [$bg, $ay, $abt];
}
function uc_user_login($app, $abm = 'username', $abn = 'password')
{
log_time("uc_user_login start");
$qr = $app->getContainer();
$z = $qr->request;
$ab = $z->getParams();
$ab = select_keys([$abm, $abn], $ab);
$abp = $ab[$abm];
$em = $ab[$abn];
if (!$abp || !$em) {
return NULL;
}
uc::init();
$v = uc::pwd_login($abp, $em);
if ($v['code'] != 0) {
ret($v['code'], $v['message']);
}
$bg = $v['data']['access_token'];
return uc_login_data($bg);
}
function check_auth($app)
{
$z = req();
$abu = false;
$abv = cfg::get('public_paths');
$fn = $z->getUri()->getPath();
if ($fn == '/') {
$abu = true;
} else {
foreach ($abv as $bp) {
if (startWith($fn, $bp)) {
$abu = true;
}
}
}
info("check_auth: {$abu} {$fn}");
if (!$abu) {
if (is_weixin()) {
$gl = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $gl);
}
ret(1, 'auth error');
}
}
function extractUserData($abw)
{
return ['githubLogin' => $abw['login'], 'githubName' => $abw['name'], 'githubId' => $abw['id'], 'repos_url' => $abw['repos_url'], 'avatar_url' => $abw['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ak, $abx = false)
{
unset($ak['passwd']);
unset($ak['salt']);
if (!$abx) {
unset($ak['token']);
}
unset($ak['access-token']);
ret($ak);
}
function cache_user($aw, $ak = null)
{
cache($aw, $ak, 7200, $ak);
$ay = null;
if ($ak) {
$ay = getArg($ak, 'userinfo');
}
return compact('token', 'userinfo');
}
$app = new \Slim\App();
ctx::app($app);
function tpl($bn, $aby = '.html')
{
$bn = $bn . $aby;
$abz = cfg::get('tpl_prefix');
$acd = "{$abz['pc']}/{$bn}";
$ace = "{$abz['mobile']}/{$bn}";
info("tpl: {$acd} | {$ace}");
return isMobile() ? $ace : $acd;
}
function req()
{
return ctx::req();
}
function get($br, $ot = '')
{
$z = req();
$u = $z->getParam($br, $ot);
if ($u == $ot) {
$acf = ctx::gets();
if (isset($acf[$br])) {
return $acf[$br];
}
}
return $u;
}
function post($br, $ot = '')
{
$z = req();
return $z->getParam($br, $ot);
}
function gets()
{
$z = req();
$v = $z->getQueryParams();
$v = array_merge($v, ctx::gets());
return $v;
}
function querystr()
{
$z = req();
return $z->getUri()->getQuery();
}
function posts()
{
$z = req();
return $z->getParsedBody();
}
function reqs()
{
$z = req();
return $z->getParams();
}
function uripath()
{
$z = req();
$fn = $z->getUri()->getPath();
if (!startWith($fn, '/')) {
$fn = '/' . $fn;
}
return $fn;
}
function host_str($pt)
{
$acg = '';
if (isset($_SERVER['HTTP_HOST'])) {
$acg = $_SERVER['HTTP_HOST'];
}
return " [ {$acg} ] " . $pt;
}
function debug($pt)
{
$m = \cfg::get('app');
if (isset($m['log_level']) && $m['log_level'] == 100) {
if (ctx::logger()) {
$pt = format_log_str($pt, getCallerStr(3));
ctx::logger()->debug(host_str($pt));
}
}
}
function warn($pt)
{
if (ctx::logger()) {
$pt = format_log_str($pt, getCallerStr(3));
ctx::logger()->warn(host_str($pt));
}
}
function info($pt)
{
if (ctx::logger()) {
$pt = format_log_str($pt, getCallerStr(3));
ctx::logger()->info(host_str($pt));
}
}
function format_log_str($pt, $ach = '')
{
if (is_array($pt)) {
$pt = json_encode($pt);
}
return "{$pt} [ ::{$ach} ]";
}
function ck_owner($dg)
{
$bc = ctx::uid();
$jq = $dg['uid'];
debug("ck_owner: {$bc} {$jq}");
return $bc == $jq;
}
function _err($br)
{
return cfg::get($br, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bt = '', $tx = 0)
{
global $__log_time__, $__log_begin_time__;
list($ry, $rz) = explode(" ", microtime());
$aci = (double) $ry + (double) $rz;
if (!$__log_time__) {
$__log_begin_time__ = $aci;
$__log_time__ = $aci;
$bp = uripath();
debug("usetime: --- {$bp} ---");
return $aci;
}
if ($tx && $tx == 'begin') {
$acj = $__log_begin_time__;
} else {
$acj = $tx ? $tx : $__log_time__;
}
$tz = $aci - $acj;
$tz *= 1000;
debug("usetime: ---  {$tz} {$bt}  ---");
$__log_time__ = $aci;
return $aci;
}
use core\Service as ms;
$p = $app->getContainer();
$p['view'] = function ($qr) {
$bo = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$bo->addExtension(new \Slim\Views\TwigExtension($qr['router'], $qr['request']->getUri()));
return $bo;
};
$p['logger'] = function ($qr) {
if (is_docker_env()) {
$ack = '/ws/log/app.log';
} else {
$acl = cfg::get('logdir');
if ($acl) {
$ack = $acl . '/app.log';
} else {
$ack = __DIR__ . '/../app.log';
}
}
$acm = ['name' => '', 'path' => $ack];
$acn = new \Monolog\Logger($acm['name']);
$acn->pushProcessor(new \Monolog\Processor\UidProcessor());
$aco = \cfg::get('app');
$lr = isset($aco['log_level']) ? $aco['log_level'] : '';
if (!$lr) {
$lr = \Monolog\Logger::INFO;
}
$acn->pushHandler(new \Monolog\Handler\StreamHandler($acm['path'], $lr));
return $acn;
};
log_time();
$p['errorHandler'] = function ($qr) {
return function ($fk, $fl, $acp) use($qr) {
info($acp);
$acq = 'Something went wrong!';
return $qr['response']->withStatus(500)->withHeader('Content-Type', 'text/html')->write($acq);
};
};
$p['notFoundHandler'] = function ($qr) {
if (!\ctx::isFoundRoute()) {
return function ($fk, $fl) use($qr) {
return $qr['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($fk, $fl) use($qr) {
return $qr['response'];
};
};
$p['ms'] = function ($qr) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($jm, $l, array $bd) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$acr = ROOT_PATH . '/routes';
if (folder_exist($acr)) {
$q = dir::scan($acr, ['type' => 'file']);
foreach ($q as $r) {
if (basename($r) != 'routes.php' && !endWith($r, '.DS_Store')) {
require_once $r;
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
$acs = cache('blockly_routes_key', function () {
return db::all('sys_blockly', ['AND' => ['code_type' => 'route']]);
}, 86400, 1);
foreach ($acs as $act) {
eval($act['phpcode']);
}
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $br, $dl = array())
{
$app->options("/hot/{$br}", function () {
ret([]);
});
$app->options("/hot/{$br}/{id}", function () {
ret([]);
});
$app->get("/hot/{$br}", function () use($dl, $br) {
$vw = $dl['objname'];
$acu = $br;
$cl = rest::getList($acu);
$xz = isset($dl['cols_map']) ? $dl['cols_map'] : [];
list($abl, $abg, $abh, $ct) = getMetaData($vw, $xz);
$abh[0] = 10;
$v['data'] = ['meta' => $abl, 'list' => $cl['data'], 'colHeaders' => $abg, 'colWidths' => $abh, 'cols' => $ct];
ret($v);
});
$app->get("/hot/{$br}/param", function () use($dl, $br) {
$vw = $dl['objname'];
$acu = $br;
$cl = rest::getList($acu);
list($abg, $abh, $ct) = getHotColMap1($acu);
$abl = ['objname' => $vw];
$abh[0] = 10;
$v['data'] = ['meta' => $abl, 'list' => [], 'colHeaders' => $abg, 'colWidths' => $abh, 'cols' => $ct];
ret($v);
});
$app->post("/hot/{$br}", function () use($dl, $br) {
$acu = $br;
$cl = rest::postData($acu);
ret($cl);
});
$app->put("/hot/{$br}/{id}", function ($z, $bl, $or) use($dl, $br) {
$acu = $br;
$ab = ctx::data();
if (isset($ab['trans-word']) && isset($ab[$ab['trans-from']])) {
$acv = $ab['trans-from'];
$acw = $ab['trans-to'];
$u = util\Pinyin::get($ab[$acv]);
$ab[$acw] = $u;
}
ctx::data($ab);
$cl = rest::putData($acu, $or['id']);
ret($cl);
});
}
function getHotColMap()
{
$acu = 'bom_part_params';
ctx::pagesize(50);
$cl = rest::getList($acu);
$acx = getKeyValues($cl['data'], 'id');
$bd = indexArray($cl['data'], 'id');
$acy = db::all('bom_part_param_prop', ['AND' => ['part_param_id' => $acx]]);
$acy = groupArray($acy, 'id');
$es = db::all('bom_part_param_opt', ['AND' => ['param_id' => $acx]]);
$es = groupArray($es, 'param_prop_id');
$xz = [];
foreach ($es as $k => $eg) {
$acz = '';
$ade = 0;
$adf = $acy[$k];
foreach ($adf as $bu => $adg) {
if ($adg['name'] == 'value') {
$ade = $adg['part_param_id'];
}
}
$acz = $bd[$ade]['name'];
if ($ade) {
}
if ($acz) {
$xz[$acz] = ['data' => $acz, 'type' => 'autocomplete', 'strict' => false, 'source' => getKeyValues($eg, 'option')];
}
}
$ab = ['rows' => $cl, 'pids' => $acx, 'props' => $acy, 'opts' => $es, 'cols_map' => $xz];
$xz = [];
return $xz;
}
function getHotColMap1($acu)
{
$adh = $acu . '_param';
$adi = $acu . '_opt';
$adj = $acu . '_opt_ext';
ctx::pagesize(50);
ctx::gets('pid', 6);
$cl = rest::getList($adh);
$acx = getKeyValues($cl['data'], 'id');
$bd = indexArray($cl['data'], 'id');
$es = db::all($adi, ['AND' => ['pid' => $acx]]);
$es = indexArray($es, 'id');
$acx = array_keys($es);
$adk = db::all($adj, ['AND' => ['pid' => $acx]]);
$adk = groupArray($adk, 'pid');
$abg = [];
$abh = [];
$ct = [];
foreach ($bd as $k => $adl) {
$abg[] = $adl['label'];
$abh[] = $adl['width'];
$ct[$adl['name']] = ['data' => $adl['name'], 'renderer' => 'html'];
}
foreach ($adk as $k => $eg) {
$acz = '';
$ade = 0;
$adm = $es[$k];
$adn = $adm['pid'];
$adl = $bd[$adn];
$ado = $adl['label'];
$acz = $adl['name'];
if ($ade) {
}
if ($acz) {
$ct[$acz] = ['data' => $acz, 'type' => 'autocomplete', 'strict' => false, 'source' => getKeyValues($eg, 'option')];
}
}
$ct = array_values($ct);
return [$abg, $abh, $ct];
$ab = ['rows' => $cl, 'pids' => $acx, 'props' => $acy, 'opts' => $es, 'cols_map' => $xz];
$xz = [];
return $xz;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $br, $dl = array())
{
$acu = $br;
$adp = "{$br}_ext";
$app->get("/hot/{$br}", function () use($acu, $adp) {
$jr = get('oid');
$ade = get('pid');
$cf = "select * from `{$acu}` pp join `{$adp}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$jr} and pp.pid={$ade}";
$cl = db::query($cf);
$ab = groupArray($cl, 'name');
$abg = ['Id', 'Oid', 'RowNum'];
$abh = [5, 5, 5];
$ct = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ai = [];
foreach ($ab as $bu => $bv) {
$abg[] = $bv[0]['label'];
$abh[] = $bv[0]['col_width'];
$ct[] = ['data' => $bu, 'renderer' => 'html'];
$adq = [];
foreach ($bv as $k => $dg) {
$ai[$dg['_rownum']][$bu] = $dg['option'];
if ($bu == 'value') {
if (!isset($ai[$dg['_rownum']]['id'])) {
$ai[$dg['_rownum']]['id'] = $dg['id'];
$ai[$dg['_rownum']]['oid'] = $jr;
$ai[$dg['_rownum']]['_rownum'] = $dg['_rownum'];
}
}
}
}
$ai = array_values($ai);
$v['data'] = ['list' => $ai, 'colHeaders' => $abg, 'colWidths' => $abh, 'cols' => $ct];
ret($v);
});
$app->get("/hot/{$br}_addprop", function () use($acu, $adp) {
$jr = get('oid');
$ade = get('pid');
$adr = get('propname');
if ($adr != 'value' && !checkOptPropVal($jr, $ade, 'value', $acu, $adp)) {
addOptProp($jr, $ade, 'value', $acu, $adp);
}
if (!checkOptPropVal($jr, $ade, $adr, $acu, $adp)) {
addOptProp($jr, $ade, $adr, $acu, $adp);
}
ret([11]);
});
$app->options("/hot/{$br}", function () {
ret([]);
});
$app->options("/hot/{$br}/{id}", function () {
ret([]);
});
$app->post("/hot/{$br}", function () use($acu, $adp) {
$ab = ctx::data();
$ade = $ab['pid'];
$jr = $ab['oid'];
$ads = getArg($ab, '_rownum');
$adg = db::row($acu, ['AND' => ['oid' => $jr, 'pid' => $ade, 'name' => 'value']]);
if (!$adg) {
addOptProp($jr, $ade, 'value', $acu, $adp);
}
$adt = $adg['id'];
$adu = db::obj()->max($adp, '_rownum', ['pid' => $adt]);
$ab = ['oid' => $jr, 'pid' => $adt, '_rownum' => $adu + 1];
db::save($adp, $ab);
$v = ['oid' => $jr, '_rownum' => $ads, 'prop' => $adg, 'maxrow' => $adu];
ret($v);
});
$app->put("/hot/{$br}/{id}", function ($z, $bl, $or) use($adp, $acu) {
$ab = ctx::data();
$ade = $ab['pid'];
$jr = $ab['oid'];
$ads = $ab['_rownum'];
$ads = getArg($ab, '_rownum');
$aw = $ab['token'];
$bc = $ab['uid'];
$dg = dissoc($ab, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($dg);
$k = key($dg);
$u = $dg[$k];
$adg = db::row($acu, ['AND' => ['pid' => $ade, 'oid' => $jr, 'name' => $k]]);
info("{$ade} {$jr} {$k}");
$adt = $adg['id'];
$adv = db::obj()->has($adp, ['AND' => ['pid' => $adt, '_rownum' => $ads]]);
if ($adv) {
debug("has cell ...");
$cf = "update {$adp} set `option`='{$u}' where _rownum={$ads} and pid={$adt}";
debug($cf);
db::exec($cf);
} else {
debug("has no cell ...");
$ab = ['oid' => $jr, 'pid' => $adt, '_rownum' => $ads, 'option' => $u];
db::save($adp, $ab);
}
$v = ['item' => $dg, 'oid' => $jr, '_rownum' => $ads, 'key' => $k, 'val' => $u, 'prop' => $adg, 'sql' => $cf];
ret($v);
});
}
function checkOptPropVal($jr, $ade, $br, $acu, $adp)
{
return db::obj()->has($acu, ['AND' => ['name' => $br, 'oid' => $jr, 'pid' => $ade]]);
}
function addOptProp($jr, $ade, $adr, $acu, $adp)
{
$br = Pinyin::get($adr);
$ab = ['oid' => $jr, 'pid' => $ade, 'label' => $adr, 'name' => $br];
$adg = db::save($acu, $ab);
$ab = ['_rownum' => 1, 'oid' => $jr, 'pid' => $adg['id']];
db::save($adp, $ab);
return $adg;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$adw = \cfg::load('mid');
if ($adw) {
foreach ($adw as $bu => $m) {
$adx = "\\{$bu}";
debug("load mid: {$adx}");
$app->add(new $adx());
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
