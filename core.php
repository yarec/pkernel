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
private static $_token = null;
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
self::$_token = self::getToken($z);
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
public static function src_data()
{
$ab = dissoc(self::$_data, ['_uptm', 'uniqid', 'token', 'uid']);
return $ab;
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
public static function token()
{
return self::$_token;
}
public static function getUcTokenUser($aw)
{
if (!$aw) {
return null;
}
$ax = cache($aw);
$ay = $ax['userinfo'] ? $ax['userinfo'] : null;
if (isset($ax['luser'])) {
$ay['id'] = $ay['uid'] = $ax['luser']['id'];
return $ay;
}
return null;
}
public static function getCubeTokenUser($aw)
{
if (!$aw) {
return null;
}
$ax = cache($aw);
$ak = $ax['user'] ? $ax['user'] : null;
if (isset($ax['luser'])) {
$ak['id'] = $ak['uid'] = $ax['luser']['id'];
return $ak;
}
return null;
}
public static function getAuthType($az, $aw)
{
if (isset($az['auth_type'])) {
return $az['auth_type'];
}
$bc = explode('$$', $aw);
if (count($bc) == 2) {
return $bc[1];
}
return null;
}
public static function getTokenUser($bd, $z)
{
$be = $z->getParam('uid');
$ak = null;
$az = $z->getParams();
$bf = self::check_appid($az);
if ($bf && check_sign($az, $bf)) {
debug("appkey: {$bf}");
$ak = ['id' => $be, 'roles' => ['admin']];
} else {
if (self::isStateless()) {
debug("isStateless");
$ak = ['id' => $be, 'role' => 'user'];
} else {
$aw = self::$_token;
$bg = \cfg::get('use_ucenter_oauth');
if ($bh = self::getAuthType($az, $aw)) {
if ($bh == 'cube') {
return self::getCubeTokenUser($aw);
}
}
if ($bg) {
return self::getUcTokenUser($aw);
}
$bi = self::getToken($z, 'access_token');
if (self::isEnableSso()) {
debug("getTokenUserBySso");
$ak = self::getTokenUserBySso($aw);
} else {
debug("get from db");
if ($aw) {
$bj = \cfg::get('disable_cache_user');
if ($bj) {
$ak = \db::row($bd, ['token' => $aw]);
} else {
$ak = cache($aw);
$ak = $ak['user'];
}
} else {
if ($bi) {
$ak = self::getAccessTokenUser($bd, $bi);
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
public static function check_appid($az)
{
$bk = getArg($az, 'appid');
if ($bk) {
$m = cfg::get('support_service_list', 'service');
if (isset($m[$bk])) {
debug("appid: {$bk} ok");
return $m[$bk];
}
}
debug("appid: {$bk} not ok");
return '';
}
public static function getTokenUserBySso($aw)
{
$ak = ms('sso')->getuserinfo(['token' => $aw])->json();
return $ak;
}
public static function getAccessTokenUser($bd, $bi)
{
$bl = \db::row('oauth_access_tokens', ['access_token' => $bi]);
if ($bl) {
$bm = strtotime($bl['expires']);
if ($bm - time() > 0) {
$ak = \db::row($bd, ['id' => $bl['user_id']]);
}
}
return $ak;
}
public static function user_tbl($bn = null)
{
if ($bn) {
self::$_user_tbl = $bn;
}
return self::$_user_tbl;
}
public static function render($bo, $bp, $bq, $ab)
{
$br = new \Slim\Views\Twig($bp, ['cache' => false]);
self::$_foundRoute = true;
return $br->render($bo, $bq, $ab);
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
$bs = str_replace(self::$_rest_prefix, '', self::uri());
$bt = explode('/', $bs);
$bu = getArg($bt, 1, '');
$bv = getArg($bt, 2, '');
return [$bu, $bv];
}
public static function rest_select_add($bw = '')
{
if ($bw) {
self::$_rest_select_add = $bw;
}
return self::$_rest_select_add;
}
public static function rest_join_add($bw = '')
{
if ($bw) {
self::$_rest_join_add = $bw;
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
public static function gets($bx = '', $by = '')
{
if (!$bx) {
return self::$_gets;
}
if (!$by) {
return self::$_gets[$bx];
}
if ($by == '_clear') {
$by = '';
}
self::$_gets[$bx] = $by;
return self::$_gets;
}
}
class cube
{
static $passport = null;
static $client = null;
static $cid = null;
static $cst = null;
public static function client()
{
if (!self::$client) {
$m = cfg::get('oauth', 'oauth')['cube'];
$bz = $m['endpoint'];
$cd = new \Gql\Client($bz, ['verify' => false]);
self::$client = $cd;
self::$cid = $m['client_id'];
self::$cst = $m['client_secret'];
}
return self::$client;
}
public static function login($ce, $cf)
{
self::client();
$cg = ['args' => ['client_id' => self::$cid, 'client_secret' => self::$cst, 'username' => $ce, 'password' => $cf], "resp" => ["access_token", "token_type", 'expires_in', 'refresh_token']];
$ab = self::client()->query('passport', $cg);
$ab = $ab['data'];
self::$passport = $ab['passport'];
return $ab;
}
public static function user()
{
$ab = self::client()->query('user', ['params' => ['access_token' => self::$passport['access_token']], 'resp' => ['id', 'username', 'email', 'phone', 'roles' => ['id', 'app_id', 'instance_id', 'role_id']]]);
$ab = $ab['data'];
return $ab;
}
public static function roles()
{
$ab = self::client()->query('roles', ['params' => ['access_token' => self::$passport['access_token']], 'resp' => ['id', 'app_id', 'instance_id', 'name', 'title', 'description']]);
$ab = $ab['data'];
return $ab;
}
public static function modules()
{
$ab = self::client()->query('modules', ['params' => ['access_token' => self::$passport['access_token']], 'resp' => ['id', 'app_id', 'type', 'name', 'url']]);
$ab = $ab['data'];
return $ab;
}
}
use Medoo\Medoo;
if (!function_exists('fixfn')) {
function fixfn($ch)
{
foreach ($ch as $ci) {
if (!function_exists($ci)) {
eval("function {$ci}(){}");
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
$ch = array('debug');
fixfn($ch);
class db
{
private static $_db_list;
private static $_db_default;
private static $_db_master;
private static $_dbc_list;
private static $_db;
private static $_dbc;
private static $_dbc_master;
private static $_ins;
private static $tbl_desc = array();
public static function init($m, $cj = true)
{
self::init_db($m, $cj);
}
public static function conns()
{
$ck['_db'] = self::queryRow('select user() as user, database() as dbname');
self::use_master_db();
$ck['_db_master'] = self::queryRow('select user() as user, database() as dbname');
self::use_default_db();
$ck['_db_default'] = self::queryRow('select user() as user, database() as dbname');
return $ck;
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
public static function init_db($m, $cj = true)
{
self::$_dbc = self::get_db_cfg($m);
$cl = self::$_dbc['database_name'];
self::$_dbc_list[$cl] = self::$_dbc;
self::$_db_list[$cl] = self::new_db(self::$_dbc);
if ($cj) {
self::use_db($cl);
}
}
public static function use_db($cl)
{
self::$_db = self::$_db_list[$cl];
self::$_dbc = self::$_dbc_list[$cl];
}
public static function use_default_db()
{
self::$_db = self::$_db_default;
}
public static function use_master_db()
{
self::$_db = self::$_db_master;
}
public static function dbc()
{
return self::$_dbc;
}
public static function switch_dbc($cm)
{
$cn = ms('master')->get(['path' => '/admin/corpins', 'data' => ['corpid' => $cm]]);
$co = $cn->json();
$co = getArg($co, 'data', []);
self::$_dbc = $co;
self::$_db = self::$_db_default = self::new_db(self::$_dbc);
}
public static function obj()
{
if (!self::$_db) {
self::$_dbc = self::$_dbc_master = self::get_db_cfg();
self::$_db = self::$_db_default = self::$_db_master = self::new_db(self::$_dbc);
info('====== init dbc =====');
$aw = \ctx::getToken(req());
$ak = \ctx::getUcTokenUser($aw);
$cm = getArg($ak, 'corpid');
if ($cm) {
self::switch_dbc($cm);
}
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
public static function desc_sql($cp)
{
if (self::db_type() == 'mysql') {
return "desc `{$cp}`";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$cp}'";
} else {
return '';
}
}
}
public static function table_cols($bu)
{
$cq = self::$tbl_desc;
if (!isset($cq[$bu])) {
$cr = self::desc_sql($bu);
if ($cr) {
$cq[$bu] = self::query($cr);
self::$tbl_desc = $cq;
debug("---------------- cache not found : {$bu}");
} else {
debug("empty desc_sql for: {$bu}");
}
}
if (!isset($cq[$bu])) {
return array();
} else {
return self::$tbl_desc[$bu];
}
}
public static function col_array($bu)
{
$cs = function ($by) use($bu) {
return $bu . '.' . $by;
};
return getKeyValues(self::table_cols($bu), 'Field', $cs);
}
public static function valid_table_col($bu, $ct)
{
$cu = self::table_cols($bu);
foreach ($cu as $cv) {
if ($cv['Field'] == $ct) {
$c = $cv['Type'];
return is_string_column($cv['Type']);
}
}
return false;
}
public static function tbl_data($bu, $ab)
{
$cu = self::table_cols($bu);
$v = [];
foreach ($cu as $cv) {
$cw = $cv['Field'];
if (isset($ab[$cw])) {
$v[$cw] = $ab[$cw];
}
}
return $v;
}
public static function test()
{
$cr = "select * from tags limit 10";
$cx = self::obj()->query($cr)->fetchAll(\PDO::FETCH_ASSOC);
var_dump($cx);
}
public static function has_st($bu, $cy)
{
$cz = '_st';
return isset($cy[$cz]) || isset($cy[$bu . '.' . $cz]);
}
public static function getWhere($bu, $de)
{
$cz = '_st';
if (!self::valid_table_col($bu, $cz)) {
return $de;
}
$cz = $bu . '._st';
if (is_array($de)) {
$df = array_keys($de);
$dg = preg_grep("/^AND\\s*#?\$/i", $df);
$dh = preg_grep("/^OR\\s*#?\$/i", $df);
$di = array_diff_key($de, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
$cy = [];
if ($di != array()) {
$cy = $di;
if (!self::has_st($bu, $cy)) {
$de[$cz] = 1;
$de = ['AND' => $de];
}
}
if (!empty($dg)) {
$l = array_values($dg);
$cy = $de[$l[0]];
if (!self::has_st($bu, $cy)) {
$de[$l[0]][$cz] = 1;
}
}
if (!empty($dh)) {
$l = array_values($dh);
$cy = $de[$l[0]];
if (!self::has_st($bu, $cy)) {
$de[$l[0]][$cz] = 1;
}
}
if (!isset($de['AND']) && !self::has_st($bu, $cy)) {
$de['AND'][$cz] = 1;
}
}
return $de;
}
public static function all_sql($bu, $de = array(), $dj = '*', $dk = null)
{
$dl = [];
if ($dk) {
$cr = self::obj()->selectContext($bu, $dl, $dk, $dj, $de);
} else {
$cr = self::obj()->selectContext($bu, $dl, $dj, $de);
}
return $cr;
}
public static function all($bu, $de = array(), $dj = '*', $dk = null)
{
$de = self::getWhere($bu, $de);
info($de);
if ($dk) {
$cx = self::obj()->select($bu, $dk, $dj, $de);
} else {
$cx = self::obj()->select($bu, $dj, $de);
}
return $cx;
}
public static function count($bu, $de = array('_st' => 1))
{
$de = self::getWhere($bu, $de);
return self::obj()->count($bu, $de);
}
public static function row_sql($bu, $de = array(), $dj = '*', $dk = '')
{
return self::row($bu, $de, $dj, $dk, true);
}
public static function row($bu, $de = array(), $dj = '*', $dk = '', $dm = null)
{
$de = self::getWhere($bu, $de);
if (!isset($de['LIMIT'])) {
$de['LIMIT'] = 1;
}
if ($dk) {
if ($dm) {
return self::obj()->selectContext($bu, $dk, $dj, $de);
}
$cx = self::obj()->select($bu, $dk, $dj, $de);
} else {
if ($dm) {
return self::obj()->selectContext($bu, $dj, $de);
}
$cx = self::obj()->select($bu, $dj, $de);
}
if ($cx) {
return $cx[0];
} else {
return null;
}
}
public static function one($bu, $de = array(), $dj = '*', $dk = '')
{
$dn = self::row($bu, $de, $dj, $dk);
$do = '';
if ($dn) {
$dp = array_keys($dn);
$do = $dn[$dp[0]];
}
return $do;
}
public static function parseUk($bu, $dq, $ab)
{
$dr = true;
info("uk: {$dq}, " . json_encode($ab));
if (is_array($dq)) {
foreach ($dq as $ds) {
if (!isset($ab[$ds])) {
$dr = false;
} else {
$dt[$ds] = $ab[$ds];
}
}
} else {
if (!isset($ab[$dq])) {
$dr = false;
} else {
$dt = [$dq => $ab[$dq]];
}
}
$du = false;
if ($dr) {
info("has uk {$dr}");
info("where: " . json_encode($dt));
if (!self::obj()->has($bu, ['AND' => $dt])) {
$du = true;
}
} else {
$du = true;
}
return [$dt, $du];
}
public static function save($bu, $ab, $dq = 'id')
{
list($dt, $du) = self::parseUk($bu, $dq, $ab);
info("isInsert: {$du}, {$bu} {$dq} " . json_encode($ab));
if ($du) {
debug("insert {$bu} : " . json_encode($ab));
self::obj()->insert($bu, $ab);
$ab['id'] = self::obj()->id();
} else {
debug("update {$bu} " . json_encode($dt));
self::obj()->update($bu, $ab, ['AND' => $dt]);
}
return $ab;
}
public static function update($bu, $ab, $de)
{
self::obj()->update($bu, $ab, $de);
}
public static function exec($cr)
{
return self::obj()->exec($cr);
}
public static function query($cr)
{
return self::obj()->query($cr)->fetchAll(\PDO::FETCH_ASSOC);
}
public static function queryRow($cr)
{
$cx = self::query($cr);
if ($cx) {
return $cx[0];
} else {
return null;
}
}
public static function queryOne($cr)
{
$dn = self::queryRow($cr);
return self::oneVal($dn);
}
public static function oneVal($dn)
{
$do = '';
if ($dn) {
$dp = array_keys($dn);
$do = $dn[$dp[0]];
}
return $do;
}
public static function updateBatch($bu, $ab, $dq = 'id')
{
$dv = $bu;
if (!is_array($ab) || empty($dv)) {
return FALSE;
}
$cr = "UPDATE `{$dv}` SET";
foreach ($ab as $bv => $dn) {
foreach ($dn as $k => $u) {
$dw[$k][] = "WHEN {$bv} THEN {$u}";
}
}
foreach ($dw as $k => $u) {
$cr .= ' `' . trim($k, '`') . '`=CASE ' . $dq . ' ' . join(' ', $u) . ' END,';
}
$cr = trim($cr, ',');
$cr .= ' WHERE ' . $dq . ' IN(' . join(',', array_keys($ab)) . ')';
return self::query($cr);
}
}
class fcache
{
const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
const CLEAR_ALL_KEY = 'CLEAR_ALL';
static $_instance = null;
protected $_options = array('cache_dir' => './cache', 'file_locking' => true, 'file_name_prefix' => 'cache', 'cache_file_umask' => 0777, 'file_life' => 100000);
static function &getInstance($dx = array())
{
if (self::$_instance === null) {
self::$_instance = new self($dx);
}
return self::$_instance;
}
static function &setOptions($dx = array())
{
return self::getInstance($dx);
}
private function __construct($dx = array())
{
if ($this->_options['cache_dir'] !== null) {
$bp = rtrim($this->_options['cache_dir'], '/') . '/';
$this->_options['cache_dir'] = $bp;
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
$dy =& self::getInstance();
if (!is_dir($l)) {
exit('file_cache: ' . $l . ' 不是一个有效路径 ');
}
if (!is_writable($l)) {
exit('file_cache: 路径 "' . $l . '" 不可写');
}
$l = rtrim($this->_options['cache_dir'], '/') . '/';
$dy->_options['cache_dir'] = $l;
}
static function save($ab, $bv = null, $dz = null)
{
$dy =& self::getInstance();
if (!$bv) {
if ($dy->_id) {
$bv = $dy->_id;
} else {
exit('file_cache:save() id 不能为空!');
}
}
$ef = time();
if ($dz) {
$ab[self::FILE_LIFE_KEY] = $ef + $dz;
} elseif ($dz != 0) {
$ab[self::FILE_LIFE_KEY] = $ef + $dy->_options['file_life'];
}
$r = $dy->_file($bv);
$ab = "\n" . " // mktime: " . $ef . "\n" . " return " . var_export($ab, true) . "\n?>";
$cn = $dy->_filePutContents($r, $ab);
return $cn;
}
static function load($bv)
{
$dy =& self::getInstance();
$ef = time();
if (!$dy->test($bv)) {
return false;
}
$eg = $dy->_file(self::CLEAR_ALL_KEY);
$r = $dy->_file($bv);
if (is_file($eg) && filemtime($eg) > filemtime($r)) {
return false;
}
$ab = $dy->_fileGetContents($r);
if (empty($ab[self::FILE_LIFE_KEY]) || $ef < $ab[self::FILE_LIFE_KEY]) {
unset($ab[self::FILE_LIFE_KEY]);
return $ab;
}
return false;
}
protected function _filePutContents($r, $eh)
{
$dy =& self::getInstance();
$ei = false;
$ej = @fopen($r, 'ab+');
if ($ej) {
if ($dy->_options['file_locking']) {
@flock($ej, LOCK_EX);
}
fseek($ej, 0);
ftruncate($ej, 0);
$ek = @fwrite($ej, $eh);
if (!($ek === false)) {
$ei = true;
}
@fclose($ej);
}
@chmod($r, $dy->_options['cache_file_umask']);
return $ei;
}
protected function _file($bv)
{
$dy =& self::getInstance();
$el = $dy->_idToFileName($bv);
return $dy->_options['cache_dir'] . $el;
}
protected function _idToFileName($bv)
{
$dy =& self::getInstance();
$dy->_id = $bv;
$x = $dy->_options['file_name_prefix'];
$ei = $x . '---' . $bv;
return $ei;
}
static function test($bv)
{
$dy =& self::getInstance();
$r = $dy->_file($bv);
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
$dy =& self::getInstance();
$dy->save('CLEAR_ALL', self::CLEAR_ALL_KEY);
}
static function del($bv)
{
$dy =& self::getInstance();
if (!$dy->test($bv)) {
return false;
}
$r = $dy->_file($bv);
return unlink($r);
}
}
class mdb
{
private static $_client;
private static $_db;
private static $_ins;
public static function obj($cl = 'myapp_dev')
{
if (!self::$_client) {
self::$_client = new \Sokil\Mongo\Client();
}
if (!self::$_db) {
self::$_db = self::$_client->{$cl};
}
return self::$_db;
}
public static function test()
{
$be = 1;
$em = self::obj()->blogs;
$en = $em->find()->findAll();
$ab = object2array($en);
$eo = 1;
foreach ($ab as $bx => $ep) {
unset($ep['_id']);
unset($ep['tid']);
unset($ep['tags']);
if (isset($ep['_intm'])) {
$ep['_intm'] = date('Y-m-d H:i:s', $ep['_intm']['sec']);
}
if (isset($ep['_uptm'])) {
$ep['_uptm'] = date('Y-m-d H:i:s', $ep['_uptm']['sec']);
}
$ep['uid'] = $be;
$v = db::save('blogs', $ep);
$eo++;
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
self::$_client = $cd = new Predis\Client(cfg::get_redis_cfg());
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
public static function init($eq = array())
{
$m = cfg::get('oauth', 'oauth')['uc'];
$m['host'] = env('UC_HOST', $m['host']);
$m['clientId'] = env('UC_CLIENT_ID', $m['clientId']);
$m['clientSecret'] = env('UC_CLIENT_SECRET', $m['clientSecret']);
$m['redirectUri'] = env('UC_REDIRECT_URI', $m['redirectUri']);
$m['username'] = env('UC_USERNAME', $m['username']);
$m['passwd'] = env('UC_PASSWD', $m['passwd']);
self::$oauth_cfg = $m;
if (isset($eq['host'])) {
self::$UC_HOST = $eq['host'];
}
}
public static function makeUrl($bs, $az = '')
{
if (!self::$oauth_cfg) {
self::init();
}
return self::$oauth_cfg['host'] . $bs . ($az ? '?' . $az : '');
}
public static function pwd_login($er = null, $es = null, $et = null, $eu = null)
{
$ev = $er ? $er : self::$oauth_cfg['username'];
$cf = $es ? $es : self::$oauth_cfg['passwd'];
$ew = $et ? $et : self::$oauth_cfg['clientId'];
$ex = $eu ? $eu : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $ew, 'client_secret' => $ex, 'grant_type' => 'password', 'username' => $ev, 'password' => $cf];
$ey = self::makeUrl(self::API['accessToken']);
$ez = curl($ey, 10, 30, $ab);
$v = json_decode($ez, true);
self::_set_pwd_user($v);
return $v;
}
public static function get_admin_token($fg = array())
{
if (isset($fg['access_token'])) {
$bi = $fg['access_token'];
} else {
$v = self::pwd_login();
$bi = $v['data']['access_token'];
}
return $bi;
}
public static function id_login($bv, $et = null, $eu = null, $cg = array())
{
$ew = $et ? $et : self::$oauth_cfg['clientId'];
$ex = $eu ? $eu : self::$oauth_cfg['clientSecret'];
$bi = self::get_admin_token($cg);
$ab = ['client_id' => $ew, 'client_secret' => $ex, 'grant_type' => 'id', 'access_token' => $bi, 'id' => $bv];
$ey = self::makeUrl(self::API['userAccessToken']);
$ez = curl($ey, 10, 30, $ab);
$v = json_decode($ez, true);
self::_set_id_user($v);
return $v;
}
public static function authurl($bk, $fh, $bi)
{
$fi = self::$oauth_cfg['host'] . "/api/sso/redirect?access_token={$bi}&app_id={$bk}&domain_id={$fh}";
return $fi;
}
public static function code_login($fj, $fk = null, $et = null, $eu = null)
{
$fl = $fk ? $fk : self::$oauth_cfg['redirectUri'];
$ew = $et ? $et : self::$oauth_cfg['clientId'];
$ex = $eu ? $eu : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $ew, 'client_secret' => $ex, 'grant_type' => 'authorization_code', 'redirect_uri' => $fl, 'code' => $fj];
$ey = self::makeUrl(self::API['accessToken']);
$ez = curl($ey, 10, 30, $ab);
$v = json_decode($ez, true);
self::_set_code_user($v);
return $v;
}
public static function user_info($bi)
{
$ey = self::makeUrl(self::API['user'], 'access_token=' . $bi);
$ez = curl($ey);
$v = json_decode($ez, true);
self::_set_user_info($v);
return $v;
}
public static function reg_user($ev, $es = '123456', $cg = array())
{
$bi = self::get_admin_token($cg);
$ab = ['username' => $ev, 'password' => $es, 'access_token' => $bi];
$ey = self::makeUrl(self::API['user']);
$ez = curl($ey, 10, 30, $ab);
$fm = json_decode($ez, true);
return $fm;
}
public static function register_user($ev, $es = '123456')
{
return self::reg_user($ev, $es);
}
public static function find_user($fg = array())
{
$bi = self::get_admin_token($fg);
$az = 'access_token=' . $bi;
if (isset($fg['username'])) {
$az .= '&username=' . $fg['username'];
}
if (isset($fg['phone'])) {
$az .= '&phone=' . $fg['phone'];
}
$ey = self::makeUrl(self::API['finduser'], $az);
$ez = curl($ey, 10, 30);
$fm = json_decode($ez, true);
return $fm;
}
public static function edit_user($bi, $ab = array())
{
$ey = self::makeUrl(self::API['user']);
$ab['access_token'] = $bi;
$cd = new \GuzzleHttp\Client();
$cn = $cd->request('PUT', $ey, ['form_params' => $ab, 'headers' => ['X-Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded']]);
$ez = $cn->getBody();
return json_decode($ez, true);
}
public static function set_user_role($bi, $fh, $fn, $fo = 'guest')
{
$ab = ['access_token' => $bi, 'domain_id' => $fh, 'user_id' => $fn, 'role_name' => $fo];
$ey = self::makeUrl(self::API['userRole']);
$ez = curl($ey, 10, 30, $ab);
return json_decode($ez, true);
}
public static function user_role($bi, $fh)
{
$ab = ['access_token' => $bi, 'domain_id' => $fh];
$ey = self::makeUrl(self::API['userRole']);
$ey = "{$ey}?access_token={$bi}&domain_id={$fh}";
$ez = curl($ey, 10, 30);
$v = json_decode($ez, true);
self::_set_user_role($v);
return $v;
}
public static function has_role($fp)
{
if (self::$user_role && isset(self::$user_role['roles'])) {
$fq = self::$user_role['roles'];
foreach ($fq as $k => $fo) {
if ($fo['name'] == $fp) {
return true;
}
}
}
return false;
}
public static function create_domain($fr, $fs, $cg = array())
{
$bi = self::get_admin_token($cg);
$ab = ['access_token' => $bi, 'domain_name' => $fr, 'description' => $fs];
$ey = self::makeUrl(self::API['createDomain']);
$ez = curl($ey, 10, 30, $ab);
$v = json_decode($ez, true);
return $v;
}
public static function user_domain($bi)
{
$ab = ['access_token' => $bi];
$ey = self::makeUrl(self::API['userdomain']);
$ey = "{$ey}?access_token={$bi}";
$ez = curl($ey, 10, 30);
$v = json_decode($ez, true);
return $v;
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
public static function test($bu, $ab)
{
}
public static function registration($ab)
{
$by = new Valitron\Validator($ab);
$ft = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$by->rules($ft);
$by->labels(['name' => '名称', 'gender' => '性别', 'birthdate' => '生日']);
if ($by->validate()) {
return 0;
} else {
err($by->errors());
}
}
}
}
namespace mid {
use FastRoute\Dispatcher;
use FastRoute\RouteParser\Std as StdParser;
class TwigMid
{
public function __invoke($fu, $fv, $fw)
{
log_time("Twig Begin");
$fv = $fw($fu, $fv);
$fx = uripath($fu);
debug(">>>>>> TwigMid START : {$fx}  <<<<<<");
if ($fy = $this->getRoutePath($fu)) {
$br = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($br->data);
}
$fz = rtrim($fy, '/');
if ($fz == '/' || !$fz) {
$fz = 'index';
}
$bq = $fz;
$ab = [];
if (isset($br->data)) {
$ab = $br->data;
if (isset($br->data['tpl'])) {
$bq = $br->data['tpl'];
}
}
$ab['uid'] = \ctx::uid();
$ab['isLogin'] = \ctx::user() ? true : false;
$ab['user'] = \ctx::user();
$ab['uri'] = \ctx::uri();
$ab['t'] = time();
$ab['domain'] = \cfg::get('wechat_callback_domain');
$ab['gdata'] = \ctx::global_view_data();
debug("<<<<<< TwigMid END : {$fx} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $br->render($fv, tpl($bq), $ab);
} else {
return $fv;
}
}
public function getRoutePath($fu)
{
$gh = \ctx::router()->dispatch($fu);
if ($gh[0] === Dispatcher::FOUND) {
$aj = \ctx::router()->lookupRoute($gh[1]);
$gi = $aj->getPattern();
$gj = new StdParser();
$gk = $gj->parse($gi);
foreach ($gk as $gl) {
foreach ($gl as $ds) {
if (is_string($ds)) {
return $ds;
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
public function __invoke($fu, $fv, $fw)
{
log_time("AuthMid Begin");
$fx = uripath($fu);
debug(">>>>>> AuthMid START : {$fx}  <<<<<<");
\ctx::init($fu);
$this->check_auth($fu, $fv);
debug("<<<<<< AuthMid END : {$fx} >>>>>");
log_time("AuthMid END");
$fv = $fw($fu, $fv);
return $fv;
}
public function isAjax($bs = '')
{
if ($bs) {
if (startWith($bs, '/rest')) {
$this->isAjax = true;
}
}
return $this->isAjax;
}
public function check_auth($z, $bo)
{
list($gm, $ak, $gn) = $this->auth_cfg();
$fx = uripath($z);
$this->isAjax($fx);
if ($fx == '/') {
return true;
}
$go = $this->check_list($gm, $fx);
if ($go) {
$this->check_admin();
}
$gp = $this->check_list($ak, $fx);
if ($gp) {
$this->check_user();
}
$gq = $this->check_list($gn, $fx);
if (!$gq) {
$this->check_user();
}
info("check_auth: {$fx} admin:[{$go}] user:[{$gp}] pub:[{$gq}]");
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
public function auth_error($gr = 1)
{
$gs = is_weixin();
$gt = isMobile();
$gu = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$gr}, is_weixin: {$gs} , is_mobile: {$gt}");
$gv = $_SERVER['REQUEST_URI'];
if ($gs) {
header("Location: {$gu}/auth/wechat?_r={$gv}");
exit;
}
if ($gt) {
header("Location: {$gu}/auth/openwechat?_r={$gv}");
exit;
}
if ($this->isAjax()) {
ret($gr, 'auth error');
} else {
header('Location: /?_r=' . $gv);
exit;
}
}
public function auth_cfg()
{
$gw = \cfg::get('auth');
return [$gw['admin'], $gw['user'], $gw['public']];
}
public function check_list($ai, $fx)
{
foreach ($ai as $bs) {
if (startWith($fx, $bs)) {
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
public function __invoke($fu, $fv, $fw)
{
$this->init($fu, $fv, $fw);
log_time("{$this->classname} Begin");
$this->path_info = uripath($fu);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($fu, $fv);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$fv = $fw($fu, $fv);
return $fv;
}
public function handelReq($z, $bo)
{
$bs = \cfg::get($this->classname, 'mid.yml');
if (is_array($bs)) {
$this->handlePathArray($bs, $z, $bo);
} else {
if (startWith($this->path_info, $bs)) {
$this->handlePath($z, $bo);
}
}
}
public function handlePathArray($gx, $z, $bo)
{
foreach ($gx as $bs => $gy) {
if (startWith($this->path_info, $bs)) {
debug("{$this->path_info} match {$bs} {$gy}");
$this->{$gy}($z, $bo);
break;
}
}
}
public function handlePath($z, $bo)
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
public function __invoke($fu, $fv, $fw)
{
log_time("RestMid Begin");
$this->path_info = uripath($fu);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($fu)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($fu)) {
$this->apiDoc($fu);
} else {
$this->handelRest($fu);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$fv = $fw($fu, $fv);
return $fv;
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
$bs = str_replace($this->rest_prefix, '', $this->path_info);
$bt = explode('/', $bs);
$bu = getArg($bt, 1, '');
$bv = getArg($bt, 2, '');
$gy = $z->getMethod();
info(" method: {$gy}, name: {$bu}, id: {$bv}");
$gz = "handle{$gy}";
$this->{$gz}($z, $bu, $bv);
}
public function handleGET($z, $bu, $bv)
{
if ($bv) {
rest::renderItem($bu, $bv);
} else {
rest::renderList($bu);
}
}
public function handlePOST($z, $bu, $bv)
{
self::beforeData($bu, 'post');
rest::renderPostData($bu);
}
public function handlePUT($z, $bu, $bv)
{
self::beforeData($bu, 'put');
rest::renderPutData($bu, $bv);
}
public function handleDELETE($z, $bu, $bv)
{
rest::delete($z, $bu, $bv);
}
public function handleOPTIONS($z, $bu, $bv)
{
sendJson([]);
}
public function beforeData($bu, $c)
{
$hi = \cfg::get('rest_maps', 'rest.yml');
if (isset($hi[$bu])) {
$m = $hi[$bu][$c];
if ($m) {
$hj = $m['xmap'];
if ($hj) {
$ab = \ctx::data();
foreach ($hj as $bx => $by) {
unset($ab[$by]);
}
\ctx::data($ab);
}
}
}
}
public function apiDoc($z)
{
$hk = rd::genApi();
echo $hk;
die;
}
}
}
namespace db {
use db;
use db\RestHelper;
class Rest
{
private static $tbl_desc = array();
public static function whereStr($de, $bu)
{
$v = '';
foreach ($de as $bx => $by) {
$gi = '/(.*)\\{(.*)\\}/i';
$bw = preg_match($gi, $bx, $hl);
$hm = '=';
if ($hl) {
$hn = $hl[1];
$hm = $hl[2];
} else {
$hn = $bx;
}
if ($ho = db::valid_table_col($bu, $hn)) {
if ($ho == 2) {
if ($hm == 'in') {
$by = implode("','", $by);
$v .= " and t1.{$hn} {$hm} ('{$by}')";
} else {
$v .= " and t1.{$hn}{$hm}'{$by}'";
}
} else {
if ($hm == 'in') {
$by = implode(',', $by);
$v .= " and t1.{$hn} {$hm} ({$by})";
} else {
$v .= " and t1.{$hn}{$hm}{$by}";
}
}
} else {
}
info("[{$bu}] [{$hn}] [{$ho}] {$v}");
}
return $v;
}
public static function getSqlFrom($bu, $hp, $be, $hq, $hr, $cg = array())
{
$hs = isset($_GET['tags']) ? 1 : isset($cg['tags']) ? 1 : 0;
$ht = isset($_GET['isar']) ? 1 : 0;
$hu = RestHelper::get_rest_xwh_tags_list();
if ($hu && in_array($bu, $hu)) {
$hs = 0;
}
$hv = isset($cg['force_ar']) || RestHelper::isAdmin() && $ht ? "1=1" : "t1.uid={$be}";
if ($hs) {
$hw = isset($_GET['tags']) ? get('tags') : $cg['tags'];
if ($hw && is_array($hw) && count($hw) == 1 && !$hw[0]) {
$hw = '';
}
$hx = '';
$hy = 'not in';
if ($hw) {
if (is_string($hw)) {
$hw = [$hw];
}
$hz = implode("','", $hw);
$hx = "and `name` in ('{$hz}')";
$hy = 'in';
$ij = " from {$bu} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$hp}\n                               where {$hv} and t._st=1  and t.tagid {$hy}\n                               (select id from tags where type='{$bu}' {$hx} )\n                               {$hr}";
} else {
$ij = " from {$bu} t1\n                              {$hp}\n                              where {$hv} and t1.id not in\n                              (select oid from tag_items where type='{$bu}')\n                              {$hr}";
}
} else {
$ik = $hv;
if (RestHelper::isAdmin()) {
if ($bu == RestHelper::user_tbl()) {
$ik = "t1.id={$be}";
}
}
$ij = "from {$bu} t1 {$hp} where {$ik} {$hq} {$hr}";
}
return $ij;
}
public static function getSql($bu, $cg = array())
{
$be = RestHelper::uid();
$il = RestHelper::get('sort', '_intm');
$im = RestHelper::get('asc', -1);
if (!db::valid_table_col($bu, $il)) {
$il = '_intm';
}
$im = $im > 0 ? 'asc' : 'desc';
$hr = " order by t1.{$il} {$im}";
$in = RestHelper::gets();
$in = un_select_keys(['sort', 'asc'], $in);
$io = RestHelper::get('_st', 1);
$de = dissoc($in, ['token', '_st']);
if ($io != 'all') {
$de['_st'] = $io;
}
$hq = self::whereStr($de, $bu);
$ip = RestHelper::get('search', '');
$iq = RestHelper::get('search-key', '');
if ($ip && $iq) {
$hq .= " and {$iq} like '%{$ip}%'";
}
$ir = RestHelper::select_add();
$hp = RestHelper::join_add();
$ij = self::getSqlFrom($bu, $hp, $be, $hq, $hr, $cg);
$cr = "select t1.* {$ir} {$ij}";
$is = "select count(*) cnt {$ij}";
$ag = RestHelper::offset();
$af = RestHelper::pagesize();
$cr .= " limit {$ag},{$af}";
return [$cr, $is];
}
public static function getResName($bu, $cg)
{
$it = getArg($cg, 'res_name', '');
if ($it) {
return $it;
}
$iu = RestHelper::get('res_id_key', '');
if ($iu) {
$iv = RestHelper::get($iu);
$bu .= '_' . $iv;
}
return $bu;
}
public static function getList($bu, $cg = array())
{
$be = RestHelper::uid();
list($cr, $is) = self::getSql($bu, $cg);
info($cr);
$cx = db::query($cr);
$ap = (int) db::queryOne($is);
$iw = RestHelper::get_rest_join_tags_list();
if ($iw && in_array($bu, $iw)) {
$ix = getKeyValues($cx, 'id');
$hw = RestHelper::get_tags_by_oid($be, $ix, $bu);
info("get tags ok: {$be} {$bu} " . json_encode($ix));
foreach ($cx as $bx => $dn) {
if (isset($hw[$dn['id']])) {
$iy = $hw[$dn['id']];
$cx[$bx]['tags'] = getKeyValues($iy, 'name');
}
}
info('set tags ok');
}
if (isset($cg['join_cols'])) {
foreach ($cg['join_cols'] as $iz => $jk) {
$jl = getArg($jk, 'jtype', '1-1');
$jm = getArg($jk, 'jkeys', []);
$jn = getArg($jk, 'jwhe', []);
$jo = getArg($jk, 'ast', ['id' => 'ASC']);
if (is_string($jk['on'])) {
$jp = 'id';
$jq = $jk['on'];
} else {
if (is_array($jk['on'])) {
$jr = array_keys($jk['on']);
$jp = $jr[0];
$jq = $jk['on'][$jp];
}
}
$ix = getKeyValues($cx, $jp);
$jn[$jq] = $ix;
$js = \db::all($iz, ['AND' => $jn, 'ORDER' => $jo]);
foreach ($js as $k => $jt) {
foreach ($cx as $bx => &$dn) {
if (isset($dn[$jp]) && isset($jt[$jq]) && $dn[$jp] == $jt[$jq]) {
if ($jl == '1-1') {
foreach ($jm as $ju => $jv) {
$dn[$jv] = $jt[$ju];
}
}
$ju = isset($jk['jkey']) ? $jk['jkey'] : $iz;
if ($jl == '1-n') {
$dn[$ju][] = $jt[$ju];
}
if ($jl == '1-n-o') {
$dn[$ju][] = $jt;
}
if ($jl == '1-1-o') {
$dn[$ju] = $jt;
}
}
}
}
}
}
$it = self::getResName($bu, $cg);
\ctx::count($ap);
$jw = ['pageinfo' => \ctx::pageinfo()];
return ['data' => $cx, 'res-name' => $it, 'count' => $ap, 'meta' => $jw];
}
public static function renderList($bu)
{
ret(self::getList($bu));
}
public static function getItem($bu, $bv)
{
$be = RestHelper::uid();
info("---GET---: {$bu}/{$bv}");
$it = "{$bu}-{$bv}";
if ($bu == 'colls') {
$ds = db::row($bu, ["{$bu}.id" => $bv], ["{$bu}.id", "{$bu}.title", "{$bu}.from_url", "{$bu}._intm", "{$bu}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($bu == 'feeds') {
$c = RestHelper::get('type');
$jx = RestHelper::get('rid');
$ds = db::row($bu, ['AND' => ['uid' => $be, 'rid' => $bv, 'type' => $c]]);
if (!$ds) {
$ds = ['rid' => $bv, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$it = "{$it}-{$c}-{$bv}";
} else {
$ds = db::row($bu, ['id' => $bv]);
}
}
if ($jy = RestHelper::rest_extra_data()) {
$ds = array_merge($ds, $jy);
}
return ['data' => $ds, 'res-name' => $it, 'count' => 1];
}
public static function renderItem($bu, $bv)
{
ret(self::getItem($bu, $bv));
}
public static function postData($bu)
{
$ab = db::tbl_data($bu, RestHelper::data());
$be = RestHelper::uid();
$hw = [];
if ($bu == 'tags') {
$hw = RestHelper::get_tag_by_name($be, $ab['name'], $ab['type']);
}
if ($hw && $bu == 'tags') {
$ab = $hw[0];
} else {
info("---POST---: {$bu} " . json_encode($ab));
unset($ab['token']);
$ab['_intm'] = date('Y-m-d H:i:s');
if (!isset($ab['uid'])) {
$ab['uid'] = $be;
}
$ab = db::tbl_data($bu, $ab);
$ab = db::save($bu, $ab);
}
return $ab;
}
public static function renderPostData($bu)
{
$ab = self::postData($bu);
ret($ab);
}
public static function putData($bu, $bv)
{
if ($bv == 0 || $bv == '' || trim($bv) == '') {
info(" PUT ID IS EMPTY !!!");
ret();
}
$be = RestHelper::uid();
$ab = RestHelper::data();
unset($ab['token']);
unset($ab['uniqid']);
self::checkOwner($bu, $bv, $be);
if (isset($ab['inc'])) {
$jz = $ab['inc'];
unset($ab['inc']);
db::exec("UPDATE {$bu} SET {$jz} = {$jz} + 1 WHERE id={$bv}");
}
if (isset($ab['dec'])) {
$jz = $ab['dec'];
unset($ab['dec']);
db::exec("UPDATE {$bu} SET {$jz} = {$jz} - 1 WHERE id={$bv}");
}
if (isset($ab['tags'])) {
RestHelper::del_tag_by_name($be, $bv, $bu);
$hw = $ab['tags'];
foreach ($hw as $kl) {
$km = RestHelper::get_tag_by_name($be, $kl, $bu);
if ($km) {
$kn = $km[0]['id'];
RestHelper::save_tag_items($be, $kn, $bv, $bu);
}
}
}
info("---PUT---: {$bu}/{$bv} " . json_encode($ab));
$ab = db::tbl_data($bu, $ab);
$ab['id'] = $bv;
db::save($bu, $ab);
return $ab;
}
public static function renderPutData($bu, $bv)
{
$ab = self::putData($bu, $bv);
ret($ab);
}
public static function delete($z, $bu, $bv)
{
$be = RestHelper::uid();
self::checkOwner($bu, $bv, $be);
db::save($bu, ['_st' => 0, 'id' => $bv]);
ret([]);
}
public static function checkOwner($bu, $bv, $be)
{
$de = ['AND' => ['id' => $bv], 'LIMIT' => 1];
$cx = db::obj()->select($bu, '*', $de);
if ($cx) {
$ds = $cx[0];
} else {
$ds = null;
}
if ($ds) {
if (array_key_exists('uid', $ds)) {
$ko = $ds['uid'];
if ($bu == RestHelper::user_tbl()) {
$ko = $ds['id'];
}
if ($ko != $be && (!RestHelper::isAdmin() || !RestHelper::isAdminRest())) {
ret(311, 'owner error');
}
} else {
if (!RestHelper::isAdmin()) {
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
class RestHelper
{
private static $_ins = null;
public static function ins($kp = null)
{
if ($kp) {
self::$_ins = $kp;
}
if (!self::$_ins && class_exists('\\db\\RestHelperIns')) {
self::$_ins = new \db\RestHelperIns();
}
return self::$_ins;
}
public static function get_rest_xwh_tags_list()
{
return self::ins()->get_rest_xwh_tags_list();
}
public static function get_rest_join_tags_list()
{
return self::ins()->get_rest_join_tags_list();
}
public static function rest_extra_data()
{
return self::ins()->rest_extra_data();
}
public static function get_tags_by_oid($be, $ix, $bu)
{
return self::ins()->get_tags_by_oid($be, $ix, $bu);
}
public static function get_tag_by_name($be, $bu, $c)
{
return self::ins()->get_tag_by_name($be, $bu, $c);
}
public static function del_tag_by_name($be, $bv, $bu)
{
return self::ins()->del_tag_by_name($be, $bv, $bu);
}
public static function save_tag_items($be, $kn, $bv, $bu)
{
return self::ins()->save_tag_items($be, $kn, $bv, $bu);
}
public static function isAdmin()
{
return self::ins()->isAdmin();
}
public static function isAdminRest()
{
return self::ins()->isAdminRest();
}
public static function user_tbl()
{
return self::ins()->user_tbl();
}
public static function data()
{
return self::ins()->data();
}
public static function uid()
{
return self::ins()->uid();
}
public static function get($bx, $kq = '')
{
return self::ins()->get($bx, $kq);
}
public static function gets()
{
return self::ins()->gets();
}
public static function select_add()
{
return self::ins()->select_add();
}
public static function join_add()
{
return self::ins()->join_add();
}
public static function offset()
{
return self::ins()->offset();
}
public static function pagesize()
{
return self::ins()->pagesize();
}
}
}
namespace db {
interface RestHelperIF
{
public function get_rest_xwh_tags_list();
public function get_rest_join_tags_list();
public function rest_extra_data();
public function get_tags_by_oid($be, $ix, $bu);
public function get_tag_by_name($be, $bu, $c);
public function del_tag_by_name($be, $bv, $bu);
public function save_tag_items($be, $kn, $bv, $bu);
public function isAdmin();
public function isAdminRest();
public function user_tbl();
public function data();
public function uid();
public function get($bx, $kq);
public function gets();
public function select_add();
public function join_add();
public function offset();
public function pagesize();
}
}
namespace db {
use db\Tagx as tag;
class RestHelperIns implements RestHelperIF
{
public function get_rest_xwh_tags_list()
{
return ['afwefwe'];
}
public function get_rest_join_tags_list()
{
return \cfg::rest('rest_join_tags_list');
}
public function rest_extra_data()
{
return \ctx::rest_extra_data();
}
public function get_tags_by_oid($be, $ix, $bu)
{
return tag::getTagsByOids($be, $ix, $bu);
}
public function get_tag_by_name($be, $bu, $c)
{
return tag::getTagByName($be, $bu, $c);
}
public function del_tag_by_name($be, $bv, $bu)
{
return tag::delTagByOid($be, $bv, $bu);
}
public function save_tag_items($be, $kn, $bv, $bu)
{
return tag::saveTagItems($be, $kn, $bv, $bu);
}
public function isAdmin()
{
return \ctx::isAdmin();
}
public function isAdminRest()
{
return \ctx::isAdminRest();
}
public function user_tbl()
{
return \ctx::user_tbl();
}
public function data()
{
return \ctx::data();
}
public function uid()
{
return \ctx::uid();
}
public function get($bx, $kq)
{
return get($bx, $kq);
}
public function gets()
{
return gets();
}
public function select_add()
{
return \ctx::rest_select_add();
}
public function join_add()
{
return \ctx::rest_join_add();
}
public function offset()
{
return \ctx::offset();
}
public function pagesize()
{
return \ctx::pagesize();
}
}
}
namespace db {
class Tagx
{
public static $tbl_name = 'tags';
public static $tbl_items_name = 'tag_items';
public static function getTagByName($be, $kl, $c)
{
$hw = \db::all(self::$tbl_name, ['AND' => ['uid' => $be, 'name' => $kl, 'type' => $c, '_st' => 1]]);
return $hw;
}
public static function delTagByOid($be, $kr, $ks)
{
info("del tag: {$be}, {$kr}, {$ks}");
$v = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $be, 'oid' => $kr, 'type' => $ks]]);
info($v);
}
public static function saveTagItems($be, $kt, $kr, $ks)
{
\db::save('tag_items', ['tagid' => $kt, 'uid' => $be, 'oid' => $kr, 'type' => $ks, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($be, $c)
{
$hw = \db::all(self::$tbl_name, ['AND' => ['uid' => $be, 'type' => $c, '_st' => 1]]);
return $hw;
}
public static function getTagsByOid($be, $kr, $c)
{
$cr = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$kr} and t2.type='{$c}' and t2._st=1";
$cx = \db::query($cr);
return getKeyValues($cx, 'name');
}
public static function getTagsByOids($be, $ku, $c)
{
if (is_array($ku)) {
$ku = implode(',', $ku);
}
$cr = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$ku}) and t2.type='{$c}' and t2._st=1";
$cx = \db::query($cr);
$ab = groupArray($cx, 'oid');
return $ab;
}
public static function countByTag($be, $kl, $c)
{
$cr = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$kl}' and t1.type='{$c}' and t1.uid={$be}";
$cx = \db::query($cr);
return [$cx[0]['cnt'], $cx[0]['id']];
}
public static function saveTag($be, $kl, $c)
{
$ab = ['uid' => $be, 'name' => $kl, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$ab = \db::save('tags', $ab);
return $ab;
}
public static function countTags($be, $kv, $bu)
{
foreach ($kv as $kl) {
list($kw, $bv) = self::countByTag($be, $kl, $bu);
echo "{$kl} {$kw} {$bv} <br>";
\db::update('tags', ['count' => $kw], ['id' => $bv]);
}
}
public static function saveRepoTags($be, $kx)
{
$bu = 'stars';
echo count($kx) . "<br>";
$kv = [];
foreach ($kx as $ky) {
$kz = $ky['repoId'];
$hw = isset($ky['tags']) ? $ky['tags'] : [];
if ($hw) {
foreach ($hw as $kl) {
if (!in_array($kl, $kv)) {
$kv[] = $kl;
}
$hw = self::getTagByName($be, $kl, $bu);
if (!$hw) {
$km = self::saveTag($be, $kl, $bu);
} else {
$km = $hw[0];
}
$kt = $km['id'];
$lm = getStarByRepoId($be, $kz);
if ($lm) {
$kr = $lm[0]['id'];
$ln = self::getTagsByOid($be, $kr, $bu);
if ($km && !in_array($kl, $ln)) {
self::saveTagItems($be, $kt, $kr, $bu);
}
} else {
echo "-------- star for {$kz} not found <br>";
}
}
} else {
}
}
self::countTags($be, $kv, $bu);
}
public static function getTagItem($lo, $be, $lp, $dq, $lq)
{
$cr = "select * from {$lp} where {$dq}={$lq} and uid={$be}";
return $lo->query($cr)->fetchAll();
}
public static function saveItemTags($lo, $be, $bu, $lr, $dq = 'id')
{
echo count($lr) . "<br>";
$kv = [];
foreach ($lr as $ls) {
$lq = $ls[$dq];
$hw = isset($ls['tags']) ? $ls['tags'] : [];
if ($hw) {
foreach ($hw as $kl) {
if (!in_array($kl, $kv)) {
$kv[] = $kl;
}
$hw = getTagByName($lo, $be, $kl, $bu);
if (!$hw) {
$km = saveTag($lo, $be, $kl, $bu);
} else {
$km = $hw[0];
}
$kt = $km['id'];
$lm = getTagItem($lo, $be, $bu, $dq, $lq);
if ($lm) {
$kr = $lm[0]['id'];
$ln = getTagsByOid($lo, $be, $kr, $bu);
if ($km && !in_array($kl, $ln)) {
saveTagItems($lo, $be, $kt, $kr, $bu);
}
} else {
echo "-------- star for {$lq} not found <br>";
}
}
} else {
}
}
countTags($lo, $be, $kv, $bu);
}
}
}
namespace core {
class Auth
{
public static function login($app, $ce = 'login', $cf = 'passwd')
{
$bd = \cfg::get('user_tbl_name');
$bg = \cfg::get('use_ucenter_oauth');
$aw = cguid();
$lt = null;
$ay = null;
$ab = \ctx::data();
$bh = $ab['auth_type'];
if ($bh) {
if ($bh == 'cube') {
info("cube auth ...");
$aw .= '$$cube';
$ak = cube_user_login($app, $ce, $cf);
$ak['luser'] = local_user('cube_uid', $ak['user']['id'], $bd);
cache_user($aw, $ak);
$ay = $ak['user'];
}
} else {
if ($bg) {
list($bi, $ay, $lu) = uc_user_login($app, $ce, $cf);
$ak = $ay;
$lt = ['access_token' => $bi, 'userinfo' => $ay, 'role_list' => $lu, 'luser' => local_user('uc_id', $ay['user_id'], $bd)];
extract(cache_user($aw, $lt));
$ay = select_keys(['username', 'phone', 'roles', 'email'], $ay);
} else {
$ak = user_login($app, $ce, $cf, $bd, 1);
if ($ak) {
$ak['username'] = $ak[$ce];
$lt = ['user' => $ak];
extract(cache_user($aw, $lt));
$ay = select_keys([$ce], $ak);
}
}
}
if ($ak) {
ret(['token' => $aw, 'userinfo' => $ay]);
} else {
ret(1, 'login error');
}
}
}
}
namespace core {
use Countable;
use ArrayAccess;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
class Dot implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
protected $items = array();
public function __construct($lv = array())
{
$this->items = $this->getArrayItems($lv);
}
public function add($dp, $l = null)
{
if (is_array($dp)) {
foreach ($dp as $k => $l) {
$this->add($k, $l);
}
} elseif (is_null($this->get($dp))) {
$this->set($dp, $l);
}
}
public function all()
{
return $this->items;
}
public function clear($dp = null)
{
if (is_null($dp)) {
$this->items = [];
return;
}
$dp = (array) $dp;
foreach ($dp as $k) {
$this->set($k, []);
}
}
public function delete($dp)
{
$dp = (array) $dp;
foreach ($dp as $k) {
if ($this->exists($this->items, $k)) {
unset($this->items[$k]);
continue;
}
$lv =& $this->items;
$lw = explode('.', $k);
$lx = array_pop($lw);
foreach ($lw as $ly) {
if (!isset($lv[$ly]) || !is_array($lv[$ly])) {
continue 2;
}
$lv =& $lv[$ly];
}
unset($lv[$lx]);
}
}
protected function exists($lz, $k)
{
return array_key_exists($k, $lz);
}
public function get($k = null, $mn = null)
{
if (is_null($k)) {
return $this->items;
}
if ($this->exists($this->items, $k)) {
return $this->items[$k];
}
if (strpos($k, '.') === false) {
return $mn;
}
$lv = $this->items;
foreach (explode('.', $k) as $ly) {
if (!is_array($lv) || !$this->exists($lv, $ly)) {
return $mn;
}
$lv =& $lv[$ly];
}
return $lv;
}
protected function getArrayItems($lv)
{
if (is_array($lv)) {
return $lv;
} elseif ($lv instanceof self) {
return $lv->all();
}
return (array) $lv;
}
public function has($dp)
{
$dp = (array) $dp;
if (!$this->items || $dp === []) {
return false;
}
foreach ($dp as $k) {
$lv = $this->items;
if ($this->exists($lv, $k)) {
continue;
}
foreach (explode('.', $k) as $ly) {
if (!is_array($lv) || !$this->exists($lv, $ly)) {
return false;
}
$lv = $lv[$ly];
}
}
return true;
}
public function isEmpty($dp = null)
{
if (is_null($dp)) {
return empty($this->items);
}
$dp = (array) $dp;
foreach ($dp as $k) {
if (!empty($this->get($k))) {
return false;
}
}
return true;
}
public function merge($k, $l = null)
{
if (is_array($k)) {
$this->items = array_merge($this->items, $k);
} elseif (is_string($k)) {
$lv = (array) $this->get($k);
$l = array_merge($lv, $this->getArrayItems($l));
$this->set($k, $l);
} elseif ($k instanceof self) {
$this->items = array_merge($this->items, $k->all());
}
}
public function pull($k = null, $mn = null)
{
if (is_null($k)) {
$l = $this->all();
$this->clear();
return $l;
}
$l = $this->get($k, $mn);
$this->delete($k);
return $l;
}
public function push($k, $l = null)
{
if (is_null($l)) {
$this->items[] = $k;
return;
}
$lv = $this->get($k);
if (is_array($lv) || is_null($lv)) {
$lv[] = $l;
$this->set($k, $lv);
}
}
public function set($dp, $l = null)
{
if (is_array($dp)) {
foreach ($dp as $k => $l) {
$this->set($k, $l);
}
return;
}
$lv =& $this->items;
foreach (explode('.', $dp) as $k) {
if (!isset($lv[$k]) || !is_array($lv[$k])) {
$lv[$k] = [];
}
$lv =& $lv[$k];
}
$lv = $l;
}
public function setArray($lv)
{
$this->items = $this->getArrayItems($lv);
}
public function setReference(array &$lv)
{
$this->items =& $lv;
}
public function toJson($k = null, $dx = 0)
{
if (is_string($k)) {
return json_encode($this->get($k), $dx);
}
$dx = $k === null ? 0 : $k;
return json_encode($this->items, $dx);
}
public function offsetExists($k)
{
return $this->has($k);
}
public function offsetGet($k)
{
return $this->get($k);
}
public function offsetSet($k, $l)
{
if (is_null($k)) {
$this->items[] = $l;
return;
}
$this->set($k, $l);
}
public function offsetUnset($k)
{
$this->delete($k);
}
public function count($k = null)
{
return count($this->get($k));
}
public function getIterator()
{
return new ArrayIterator($this->items);
}
public function jsonSerialize()
{
return $this->items;
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
public function __construct($mo = '')
{
if ($mo) {
$this->service = $mo;
$cg = self::$_services[$this->service];
$mp = $cg['url'];
debug("init client: {$mp}");
$this->client = new Client(['base_uri' => $mp, 'timeout' => 12.0]);
}
}
public static function add($cg = array())
{
if ($cg) {
$bu = $cg['name'];
if (!isset(self::$_services[$bu])) {
self::$_services[$bu] = $cg;
}
}
}
public static function init()
{
$mq = \cfg::get('service_list', 'service');
if ($mq) {
foreach ($mq as $m) {
self::add($m);
}
}
}
public function getRest($mo, $x = '/rest')
{
return $this->getService($mo, $x . '/');
}
public function getService($mo, $x = '')
{
if (isset(self::$_services[$mo])) {
if (!isset(self::$_ins[$mo])) {
self::$_ins[$mo] = new Service($mo);
}
}
if (isset(self::$_ins[$mo])) {
$kp = self::$_ins[$mo];
if ($x) {
$kp->setPrefix($x);
}
return $kp;
} else {
return null;
}
}
public function setPrefix($x)
{
$this->prefix = $x;
}
public function json()
{
$bw = $this->body();
$ab = json_decode($bw, true);
return $ab;
}
public function body()
{
if ($this->resp) {
return $this->resp->getBody();
}
}
public function __call($gy, $mr)
{
$cg = self::$_services[$this->service];
$mp = $cg['url'];
$bk = $cg['appid'];
$bf = $cg['appkey'];
$ms = getArg($mr, 0, []);
$ab = getArg($ms, 'data', []);
$ab = array_merge($ab, $_GET);
unset($mt['token']);
$ab['appid'] = $bk;
$ab['date'] = date("Y-m-d H:i:s");
$ab['sign'] = gen_sign($ab, $bf);
$mu = getArg($ms, 'path', '');
$mv = getArg($ms, 'suffix', '');
$mu = $this->prefix . $mu . $mv;
$gy = strtoupper($gy);
debug("api_url: {$bk} {$bf} {$mp}");
debug("api_name: {$mu} [{$gy}]");
debug("data: " . json_encode($ab));
try {
if (in_array($gy, ['GET'])) {
$mw = $mx == 'GET' ? 'query' : 'form_params';
$this->resp = $this->client->request($gy, $mu, [$mw => $ab]);
}
} catch (Exception $e) {
}
return $this;
}
}
}
namespace core {
trait PropGeneratorTrait
{
public function __get($my)
{
$gy = 'get' . ucfirst($my);
if (method_exists($this, $gy)) {
$mz = new ReflectionMethod($this, $gy);
if (!$mz->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $my)) {
return $this->{$my};
}
}
public function __set($my, $l)
{
$gy = 'set' . ucfirst($my);
if (method_exists($this, $gy)) {
$mz = new ReflectionMethod($this, $gy);
if (!$mz->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $my)) {
$this->{$my} = $l;
}
}
}
}
namespace core {
class TreeBuilder
{
protected $leafIndex = array();
protected $tree = array();
protected $stack;
protected $pid_key = 'pid';
protected $name_key = 'name';
protected $children_key = 'children';
protected $ext_keys = array();
protected $pick_node_id = 0;
function __construct($ab, $cg = array())
{
$this->stack = $ab;
if (isset($cg['pid_key'])) {
$this->pid_key = $cg['pid_key'];
}
if (isset($cg['name_key'])) {
$this->name_key = $cg['name_key'];
}
if (isset($cg['children_key'])) {
$this->children_key = $cg['children_key'];
}
if (isset($cg['ext_keys'])) {
$this->ext_keys = $cg['ext_keys'];
}
if (isset($cg['pnid'])) {
$this->pick_node_id = $cg['pnid'];
}
$no = 100;
while (count($this->stack) && $no > 0) {
$no -= 1;
debug("count stack: " . count($this->stack));
$this->branchify(array_shift($this->stack));
}
}
protected function branchify(&$np)
{
if ($this->pick_node_id) {
if ($np['id'] == $this->pick_node_id) {
$this->addLeaf($this->tree, $np);
return;
}
} else {
if (null === $np[$this->pid_key] || 0 == $np[$this->pid_key]) {
$this->addLeaf($this->tree, $np);
return;
}
}
if (isset($this->leafIndex[$np[$this->pid_key]])) {
$this->addLeaf($this->leafIndex[$np[$this->pid_key]][$this->children_key], $np);
} else {
debug("back to stack: " . json_encode($np) . json_encode($this->leafIndex));
$this->stack[] = $np;
}
}
protected function addLeaf(&$nq, $np)
{
$nr = array('id' => $np['id'], $this->name_key => $np['name'], 'data' => $np, $this->children_key => array());
foreach ($this->ext_keys as $bx => $by) {
if (isset($np[$bx])) {
$nr[$by] = $np[$bx];
}
}
$nq[] = $nr;
$this->leafIndex[$np['id']] =& $nq[count($nq) - 1];
}
protected function addChild($nq, $np)
{
$this->leafIndex[$np['id']] &= $nq[$this->children_key][] = $np;
}
public function getTree()
{
return $this->tree;
}
}
}
namespace {
if (getenv('WHOOPS_ENABLED') == 'yes') {
$ns = new \Whoops\Run();
$ns->pushHandler(new \Whoops\Handler\PrettyPageHandler());
$ns->register();
}
function getCaller($nt = NULL)
{
$nu = debug_backtrace();
$nv = $nu[2];
if (isset($nt)) {
return $nv[$nt];
} else {
return $nv;
}
}
function getCallerStr($nw = 4)
{
$nu = debug_backtrace();
$nv = $nu[2];
$nx = $nu[1];
$ny = $nv['function'];
$nz = isset($nv['class']) ? $nv['class'] : '';
$op = $nx['file'];
$oq = $nx['line'];
if ($nw == 4) {
$bw = "{$nz} {$ny} {$op} {$oq}";
} elseif ($nw == 3) {
$bw = "{$nz} {$ny} {$oq}";
} else {
$bw = "{$nz} {$oq}";
}
return $bw;
}
function wlog($bs, $or, $os)
{
if (is_dir($bs)) {
$ot = date('Y-m-d', time());
$os .= "\n";
file_put_contents($bs . "/{$or}-{$ot}.log", $os, FILE_APPEND);
}
}
function folder_exist($ou)
{
$bs = realpath($ou);
return ($bs !== false and is_dir($bs)) ? $bs : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($ab, $ov)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$ow = $m['symmetric_key'];
$ox = $m['hmac_key'];
$oy = new AES_SHA($ow, $ox);
return $oy->encrypt(serialize($ab), $ov);
}
function decrypt($ab)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$ow = $m['symmetric_key'];
$ox = $m['hmac_key'];
$oy = new AES_SHA($ow, $ox);
return unserialize($oy->decrypt($ab));
}
function encrypt_cookie($oz)
{
return encrypt($oz->getData(), $oz->getExpiration());
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($eh, $pq = 'DECODE', $k = '', $pr = 0)
{
$ps = 4;
$k = md5($k ? $k : UC_KEY);
$pt = md5(substr($k, 0, 16));
$pu = md5(substr($k, 16, 16));
$pv = $ps ? $pq == 'DECODE' ? substr($eh, 0, $ps) : substr(md5(microtime()), -$ps) : '';
$pw = $pt . md5($pt . $pv);
$px = strlen($pw);
$eh = $pq == 'DECODE' ? base64_decode(substr($eh, $ps)) : sprintf('%010d', $pr ? $pr + time() : 0) . substr(md5($eh . $pu), 0, 16) . $eh;
$py = strlen($eh);
$ei = '';
$pz = range(0, 255);
$qr = array();
for ($eo = 0; $eo <= 255; $eo++) {
$qr[$eo] = ord($pw[$eo % $px]);
}
for ($qs = $eo = 0; $eo < 256; $eo++) {
$qs = ($qs + $pz[$eo] + $qr[$eo]) % 256;
$ek = $pz[$eo];
$pz[$eo] = $pz[$qs];
$pz[$qs] = $ek;
}
for ($qt = $qs = $eo = 0; $eo < $py; $eo++) {
$qt = ($qt + 1) % 256;
$qs = ($qs + $pz[$qt]) % 256;
$ek = $pz[$qt];
$pz[$qt] = $pz[$qs];
$pz[$qs] = $ek;
$ei .= chr(ord($eh[$eo]) ^ $pz[($pz[$qt] + $pz[$qs]) % 256]);
}
if ($pq == 'DECODE') {
if ((substr($ei, 0, 10) == 0 || substr($ei, 0, 10) - time() > 0) && substr($ei, 10, 16) == substr(md5(substr($ei, 26) . $pu), 0, 16)) {
return substr($ei, 26);
} else {
return '';
}
} else {
return $pv . str_replace('=', '', base64_encode($ei));
}
}
function object2array(&$qu)
{
$qu = json_decode(json_encode($qu), true);
return $qu;
}
function getKeyValues($ab, $k, $cs = null)
{
if (!$cs) {
$cs = function ($by) {
return $by;
};
}
$bc = array();
if ($ab && is_array($ab)) {
foreach ($ab as $ds) {
if (isset($ds[$k]) && $ds[$k]) {
$u = $ds[$k];
if ($cs) {
$u = $cs($u);
}
$bc[] = $u;
}
}
}
return array_unique($bc);
}
if (!function_exists('indexArray')) {
function indexArray($ab, $k, $fg = null)
{
$bc = array();
if ($ab && is_array($ab)) {
foreach ($ab as $ds) {
if (!isset($ds[$k]) || !$ds[$k] || !is_scalar($ds[$k])) {
continue;
}
if (!$fg) {
$bc[$ds[$k]] = $ds;
} else {
if (is_string($fg)) {
$bc[$ds[$k]] = $ds[$fg];
} else {
if (is_array($fg)) {
$qv = [];
foreach ($fg as $bx => $by) {
$qv[$by] = $ds[$by];
}
$bc[$ds[$k]] = $ds[$fg];
}
}
}
}
}
return $bc;
}
}
if (!function_exists('groupArray')) {
function groupArray($lz, $k)
{
if (!is_array($lz) || !$lz) {
return array();
}
$ab = array();
foreach ($lz as $ds) {
if (isset($ds[$k]) && $ds[$k]) {
$ab[$ds[$k]][] = $ds;
}
}
return $ab;
}
}
function select_keys($dp, $ab)
{
$v = [];
foreach ($dp as $k) {
if (isset($ab[$k])) {
$v[$k] = $ab[$k];
} else {
$v[$k] = '';
}
}
return $v;
}
function un_select_keys($dp, $ab)
{
$v = [];
foreach ($ab as $bx => $ds) {
if (!in_array($bx, $dp)) {
$v[$bx] = $ds;
}
}
return $v;
}
function copyKey($ab, $qw, $qx)
{
foreach ($ab as &$ds) {
$ds[$qx] = $ds[$qw];
}
return $ab;
}
function addKey($ab, $k, $u)
{
foreach ($ab as &$ds) {
$ds[$k] = $u;
}
return $ab;
}
function dissoc($lz, $dp)
{
if (is_array($dp)) {
foreach ($dp as $k) {
unset($lz[$k]);
}
} else {
unset($lz[$dp]);
}
return $lz;
}
function sortIdx($ab)
{
$qy = [];
foreach ($ab as $bx => $by) {
$qy[$by] = ['_sort' => $bx + 1];
}
return $qy;
}
function insertAt($lv, $qz, $l)
{
array_splice($lv, $qz, 0, [$l]);
return $lv;
}
function getArg($ms, $rs, $mn = '')
{
if (isset($ms[$rs])) {
return $ms[$rs];
} else {
return $mn;
}
}
function permu($au, $dk = ',')
{
$ai = [];
if (is_string($au)) {
$rt = str_split($au);
} else {
$rt = $au;
}
sort($rt);
$ru = count($rt) - 1;
$rv = $ru;
$ap = 1;
$ds = implode($dk, $rt);
$ai[] = $ds;
while (true) {
$rw = $rv--;
if ($rt[$rv] < $rt[$rw]) {
$rx = $ru;
while ($rt[$rv] > $rt[$rx]) {
$rx--;
}
list($rt[$rv], $rt[$rx]) = array($rt[$rx], $rt[$rv]);
for ($eo = $ru; $eo > $rw; $eo--, $rw++) {
list($rt[$eo], $rt[$rw]) = array($rt[$rw], $rt[$eo]);
}
$ds = implode($dk, $rt);
$ai[] = $ds;
$rv = $ru;
$ap++;
}
if ($rv == 0) {
break;
}
}
return $ai;
}
function combin($bc, $ry, $rz = ',')
{
$ei = array();
if ($ry == 1) {
return $bc;
}
if ($ry == count($bc)) {
$ei[] = implode($rz, $bc);
return $ei;
}
$st = $bc[0];
unset($bc[0]);
$bc = array_values($bc);
$su = combin($bc, $ry - 1, $rz);
foreach ($su as $sv) {
$sv = $st . $rz . $sv;
$ei[] = $sv;
}
unset($su);
$sw = combin($bc, $ry, $rz);
foreach ($sw as $sv) {
$ei[] = $sv;
}
unset($sw);
return $ei;
}
function getExcelCol($ct)
{
$bc = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($ct == 0) {
return '';
}
return getExcelCol((int) (($ct - 1) / 26)) . $bc[$ct % 26];
}
function getExcelPos($dn, $ct)
{
return getExcelCol($ct) . $dn;
}
function sendJSON($ab)
{
$sx = cfg::get('aca');
if (isset($sx['origin'])) {
header("Access-Control-Allow-Origin: {$sx['origin']}");
}
$sy = "Content-Type, Authorization, Accept,X-Requested-With";
if (isset($sx['headers'])) {
$sy = $sx['headers'];
}
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: {$sy}");
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
function succ($bc = array(), $sz = 'succ', $tu = 1)
{
$ab = $bc;
$tv = 0;
$tw = 1;
$ap = 0;
$v = array($sz => $tu, 'errormsg' => '', 'errorfield' => '');
if (isset($bc['data'])) {
$ab = $bc['data'];
}
$v['data'] = $ab;
if (isset($bc['total_page'])) {
$v['total_page'] = $bc['total_page'];
}
if (isset($bc['cur_page'])) {
$v['cur_page'] = $bc['cur_page'];
}
if (isset($bc['count'])) {
$v['count'] = $bc['count'];
}
if (isset($bc['res-name'])) {
$v['res-name'] = $bc['res-name'];
}
if (isset($bc['meta'])) {
$v['meta'] = $bc['meta'];
}
sendJSON($v);
}
function fail($bc = array(), $sz = 'succ', $tx = 0)
{
$k = $os = '';
if (count($bc) > 0) {
$dp = array_keys($bc);
$k = $dp[0];
$os = $bc[$k][0];
}
$v = array($sz => $tx, 'errormsg' => $os, 'errorfield' => $k);
sendJSON($v);
}
function code($bc = array(), $fj = 0)
{
if (is_string($fj)) {
}
if ($fj == 0) {
succ($bc, 'code', 0);
} else {
fail($bc, 'code', $fj);
}
}
function ret($bc = array(), $fj = 0, $jz = '')
{
$qt = $bc;
$ty = $fj;
if (is_numeric($bc) || is_string($bc)) {
$ty = $bc;
$qt = array();
if (is_array($fj)) {
$qt = $fj;
} else {
$fj = $fj === 0 ? '' : $fj;
$qt = array($jz => array($fj));
}
}
code($qt, $ty);
}
function err($tz)
{
code($tz, 1);
}
function downloadExcel($uv, $el)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $el . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$uv->save('php://output');
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
function curl($ey, $uw = 10, $ux = 30, $uy = '', $gy = 'post')
{
$uz = curl_init($ey);
curl_setopt($uz, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($uz, CURLOPT_CONNECTTIMEOUT, $uw);
curl_setopt($uz, CURLOPT_HEADER, 0);
curl_setopt($uz, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($uz, CURLOPT_TIMEOUT, $ux);
if (file_exists(cacert_file())) {
curl_setopt($uz, CURLOPT_CAINFO, cacert_file());
}
if ($uy) {
if (is_array($uy)) {
$uy = http_build_query($uy);
}
if ($gy == 'post') {
curl_setopt($uz, CURLOPT_POST, 1);
} else {
if ($gy == 'put') {
curl_setopt($uz, CURLOPT_CUSTOMREQUEST, "put");
}
}
curl_setopt($uz, CURLOPT_POSTFIELDS, $uy);
}
$ei = curl_exec($uz);
if (curl_errno($uz)) {
return '';
}
curl_close($uz);
return $ei;
}
function curl_header($ey, $uw = 10, $ux = 30)
{
$uz = curl_init($ey);
curl_setopt($uz, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($uz, CURLOPT_CONNECTTIMEOUT, $uw);
curl_setopt($uz, CURLOPT_HEADER, 1);
curl_setopt($uz, CURLOPT_NOBODY, 1);
curl_setopt($uz, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($uz, CURLOPT_TIMEOUT, $ux);
if (file_exists(cacert_file())) {
curl_setopt($uz, CURLOPT_CAINFO, cacert_file());
}
$ei = curl_exec($uz);
if (curl_errno($uz)) {
return '';
}
return $ei;
}
function startWith($bw, $sv)
{
return strpos($bw, $sv) === 0;
}
function endWith($vw, $vx)
{
$vy = strlen($vx);
if ($vy == 0) {
return true;
}
return substr($vw, -$vy) === $vx;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $ab, $vz = false, $jz = '')
{
$lz = getKeyValues($ab, $k);
if (!$lz) {
return '';
}
if ($vz) {
foreach ($lz as $bx => $by) {
$lz[$bx] = "'{$by}'";
}
}
$bw = implode(',', $lz);
if ($jz) {
$k = $jz;
}
return " {$k} in ({$bw})";
}
function get_top_domain($ey)
{
$gi = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($gi, $ey, $wx);
if (count($wx) > 0) {
return $wx[0];
} else {
$wy = parse_url($ey);
$wz = $wy["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($wz))), $wz)) {
return $wz;
} else {
$bc = explode(".", $wz);
$ap = count($bc);
$xy = array("com", "net", "org", "3322");
if (in_array($bc[$ap - 2], $xy)) {
$gu = $bc[$ap - 3] . "." . $bc[$ap - 2] . "." . $bc[$ap - 1];
} else {
$gu = $bc[$ap - 2] . "." . $bc[$ap - 1];
}
return $gu;
}
}
}
function genID($nx)
{
list($xz, $yz) = explode(" ", microtime());
$abc = rand(0, 100);
return $nx . $yz . substr($xz, 2, 6);
}
function cguid($abd = false)
{
mt_srand((double) microtime() * 10000);
$abe = md5(uniqid(rand(), true));
return $abd ? strtoupper($abe) : $abe;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$abf = cguid();
$abg = chr(45);
$abh = chr(123) . substr($abf, 0, 8) . $abg . substr($abf, 8, 4) . $abg . substr($abf, 12, 4) . $abg . substr($abf, 16, 4) . $abg . substr($abf, 20, 12) . chr(125);
return $abh;
}
}
function randstr($kw = 6)
{
return substr(md5(rand()), 0, $kw);
}
function hashsalt($cf, $abi = '')
{
$abi = $abi ? $abi : randstr(10);
$abj = md5(md5($cf) . $abi);
return [$abj, $abi];
}
function gen_letters($kw = 26)
{
$sv = '';
for ($eo = 65; $eo < 65 + $kw; $eo++) {
$sv .= strtolower(chr($eo));
}
return $sv;
}
function gen_sign($az, $aw = null)
{
if ($aw == null) {
return false;
}
return strtoupper(md5(strtoupper(md5(assemble($az))) . $aw));
}
function assemble($az)
{
if (!is_array($az)) {
return null;
}
ksort($az, SORT_STRING);
$abk = '';
foreach ($az as $k => $u) {
$abk .= $k . (is_array($u) ? assemble($u) : $u);
}
return $abk;
}
function check_sign($az, $aw = null)
{
$abk = getArg($az, 'sign');
$abl = getArg($az, 'date');
$abm = strtotime($abl);
$abn = time();
$abo = $abn - $abm;
debug("check_sign : {$abn} - {$abm} = {$abo}");
if (!$abl || $abn - $abm > 60) {
debug("check_sign fail : {$abl} delta > 60");
return false;
}
unset($az['sign']);
$abp = gen_sign($az, $aw);
debug("{$abk} -- {$abp}");
return $abk == $abp;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$abq = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$abq = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$abq = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$abq = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$abq = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$abq = getenv("REMOTE_ADDR");
} else {
$abq = "Unknown";
}
}
}
}
}
}
return $abq;
}
function getRIP()
{
$abq = $_SERVER["REMOTE_ADDR"];
return $abq;
}
function env($k = 'DEV_MODE', $mn = '')
{
$l = getenv($k);
return $l ? $l : $mn;
}
function vpath()
{
$bs = getenv("VENDER_PATH");
if ($bs) {
return $bs;
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
$abr = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $abr) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
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
function cache($k, $cs = null, $yz = 10, $abs = 0)
{
$abt = new FilesystemCache();
if ($cs) {
if (is_callable($cs)) {
if ($abs || !$abt->has($k)) {
$ab = $cs();
debug("--------- fn: no cache for [{$k}] ----------");
$abt->set($k, $ab, $yz);
} else {
$ab = $abt->get($k);
debug("======= fn: data from cache [{$k}] ========");
}
} else {
debug("--------- set cache for [{$k}] ---------- : " . json_encode($cs));
$abt->set($k, $cs, $yz);
$ab = $cs;
}
} else {
debug("--------- get cache for [{$k}] ---------- ");
$ab = $abt->get($k);
}
return $ab;
}
function cache_del($k)
{
$abt = new FilesystemCache();
$abt->delete($k);
debug("!!!!!!--------- delete cache for [{$k}] ----------!!!!!!");
}
function cache_clear()
{
$abt = new FilesystemCache();
$abt->clear();
debug("!!!!!!--------- clear all cache ----------!!!!!!");
}
function baseModel($abu)
{
return '<' . <<<EOF
?php
namespace Entities {
class {$abu}
{
    protected \$id;
    protected \$intm;
    protected \$st;

EOF;
}
function baseArray($abu, $dv)
{
return array("Entities\\{$abu}" => array('type' => 'entity', 'table' => $dv, 'id' => array('id' => array('type' => 'integer', 'id' => true, 'generator' => array('strategy' => 'IDENTITY'))), 'fields' => array('intm' => array('type' => 'datetime', 'column' => '_intm'), 'st' => array('type' => 'integer', 'column' => '_st', 'options' => array('default' => 1)))));
}
function genObj($abu)
{
$abv = array_merge(\db::col_array('sys_objects'), ['sys_object_item.name(itemname)', 'sys_object_item.colname', 'sys_object_item.type', 'sys_object_item.length', 'sys_object_item.default', 'sys_object_item.comment']);
$dk = ['[>]sys_object_item' => ['id' => 'oid']];
$dt = ['AND' => ['sys_objects.name' => $abu], 'ORDER' => ['sys_objects.id' => 'DESC']];
$cx = \db::all('sys_objects', $dt, $abv, $dk);
if ($cx) {
$dv = $cx[0]['table'];
$ab = baseArray($abu, $dv);
$abw = baseModel($abu);
foreach ($cx as $dn) {
if (!$dn['itemname']) {
continue;
}
$abx = $dn['colname'] ? $dn['colname'] : $dn['itemname'];
$jz = ['type' => "{$dn['type']}", 'column' => "{$abx}", 'options' => array('default' => "{$dn['default']}", 'comment' => "{$dn['comment']}")];
$ab['Entities\\' . $abu]['fields'][$dn['itemname']] = $jz;
$abw .= "    protected \${$dn['itemname']}; \n";
}
$abw .= '}}';
}
return [$ab, $abw];
}
function writeObjFile($abu)
{
list($ab, $abw) = genObj($abu);
$aby = \Symfony\Component\Yaml\Yaml::dump($ab);
$abz = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$acd = $abz . '/src/objs';
if (!is_dir($acd)) {
mkdir($acd);
}
file_put_contents("{$acd}/{$abu}.php", $abw);
file_put_contents("{$acd}/Entities.{$abu}.dcm.yml", $aby);
}
function sync_to_db($ace = 'run')
{
echo $ace;
$abz = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$ace = "cd {$abz} && sh ./{$ace}.sh";
exec($ace, $lz);
foreach ($lz as $ds) {
echo \SqlFormatter::format($ds);
}
}
function gen_schema($acf, $acg, $ach = false, $aci = false)
{
$acj = true;
$ack = ROOT_PATH . '/tools/bin/db';
$acl = [$ack . "/yml", $ack . "/src/objs"];
$e = \Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($acl, $acj);
$acm = \Doctrine\ORM\EntityManager::create($acf, $e);
$acn = $acm->getConnection()->getDatabasePlatform();
$acn->registerDoctrineTypeMapping('enum', 'string');
$aco = [];
foreach ($acg as $acp) {
$acq = $acp['name'];
include_once "{$ack}/src/objs/{$acq}.php";
$aco[] = $acm->getClassMetadata('Entities\\' . $acq);
}
$acr = new \Doctrine\ORM\Tools\SchemaTool($acm);
$acs = $acr->getUpdateSchemaSql($aco, true);
if (!$acs) {
echo "Nothing to do.";
}
$act = [];
foreach ($acs as $ds) {
if (startWith($ds, 'DROP')) {
$act[] = $ds;
}
echo \SqlFormatter::format($ds);
}
if ($ach && !$act || $aci) {
$v = $acr->updateSchema($aco, true);
}
}
function gen_dbc_schema($acg)
{
$acu = \db::dbc();
$acf = ['driver' => 'pdo_mysql', 'host' => $acu['server'], 'user' => $acu['username'], 'password' => $acu['password'], 'dbname' => $acu['database_name']];
echo "Gen Schema for : {$acu['database_name']} <br>";
$ach = get('write', false);
$acv = get('force', false);
gen_schema($acf, $acg, $ach, $acv);
}
function gen_corp_schema($cm, $acg)
{
\db::switch_dbc($cm);
gen_dbc_schema($acg);
}
function buildcmd($cg = array())
{
$acw = new ptlis\ShellCommand\CommandBuilder();
$ms = ['LC_CTYPE=en_US.UTF-8'];
if (isset($cg['args'])) {
$ms = $cg['args'];
}
if (isset($cg['add_args'])) {
$ms = array_merge($ms, $cg['add_args']);
}
$acx = $acw->setCommand('/usr/bin/env')->addArguments($ms)->buildCommand();
return $acx;
}
function exec_git($cg = array())
{
$bs = '.';
if (isset($cg['path'])) {
$bs = $cg['path'];
}
$ms = ["/usr/bin/git", "--git-dir={$bs}/.git", "--work-tree={$bs}"];
$ace = 'status';
if (isset($cg['cmd'])) {
$ace = $cg['cmd'];
}
$ms[] = $ace;
$acx = buildcmd(['add_args' => $ms, $ace]);
$ei = $acx->runSynchronous();
return $ei->getStdOutLines();
}
use db\Rest as rest;
function getMetaData($abu, $acy = array())
{
ctx::pagesize(50);
$acg = db::all('sys_objects');
$acz = array_filter($acg, function ($by) use($abu) {
return $by['name'] == $abu;
});
$acz = array_shift($acz);
$ade = $acz['id'];
$adf = db::all('sys_object_item', ['oid' => $ade]);
$adg = ['Id'];
$adh = [0];
$adi = [0.1];
$dj = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($adf as $ds) {
$bu = $ds['name'];
$abx = $ds['colname'] ? $ds['colname'] : $bu;
$c = $ds['type'];
$mn = $ds['default'];
$adj = $ds['col_width'];
$adk = $ds['readonly'] ? ture : false;
$adl = $ds['is_meta'];
if ($adl) {
$adg[] = $bu;
$adh[$abx] = $bu;
$adi[] = (double) $adj;
if (in_array($abx, array_keys($acy))) {
$dj[] = $acy[$abx];
} else {
$dj[] = ['data' => $abx, 'renderer' => 'html', 'readOnly' => $adk];
}
}
}
$adg[] = "InTm";
$adg[] = "St";
$adi[] = 60;
$adi[] = 10;
$dj[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$dj[] = ['data' => "_st", 'renderer' => "html"];
$jw = ['objname' => $abu];
return [$jw, $adg, $adh, $adi, $dj];
}
function getHotData($abu, $acy = array())
{
$adg[] = "InTm";
$adg[] = "St";
$adi[] = 60;
$adi[] = 10;
$dj[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$dj[] = ['data' => "_st", 'renderer' => "html"];
$jw = ['objname' => $abu];
return [$jw, $adg, $adi, $dj];
}
function fixfn($ch)
{
foreach ($ch as $ci) {
if (!function_exists($ci)) {
eval("function {$ci}(){}");
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
function ms($bu)
{
return \ctx::container()->ms->getService($bu);
}
function rms($bu, $x = 'rest')
{
return \ctx::container()->ms->getRest($bu, $x);
}
function idxtree($adm, $adn)
{
$ix = [];
$ab = \db::all($adm, ['pid' => $adn]);
$ado = getKeyValues($ab, 'id');
if ($ado) {
foreach ($ado as $adn) {
$ix = array_merge($ix, idxtree($adm, $adn));
}
}
return array_merge($ado, $ix);
}
function treelist($adm, $adn)
{
$nr = \db::row($adm, ['id' => $adn]);
$adp = $nr['sub_ids'];
$adp = json_decode($adp, true);
$adq = \db::all($adm, ['id' => $adp]);
$adr = 0;
foreach ($adq as $bx => $ads) {
if ($ads['pid'] == $adn) {
$adq[$bx]['pid'] = 0;
$adr++;
}
}
if ($adr < 2) {
$adq[] = [];
}
return $adq;
return array_merge([$nr], $adq);
}
function switch_domain($aw, $cm)
{
$ak = cache($aw);
$ak['userinfo']['corpid'] = $cm;
cache_user($aw, $ak);
$adt = [];
$adu = ms('master');
if ($adu) {
$cn = ms('master')->get(['path' => '/master/corp/apps', 'data' => ['corpid' => $cm]]);
$adt = $cn->json();
$adt = getArg($adt, 'data');
}
return $adt;
}
function auto_reg_user($adv = 'username', $adw = 'password', $cp = 'user', $adx = 0)
{
$ce = randstr(10);
$cf = randstr(6);
$ab = ["{$adv}" => $ce, "{$adw}" => $cf, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($adx) {
list($cf, $abi) = hashsalt($cf);
$ab[$adw] = $cf;
$ab['salt'] = $abi;
} else {
$ab[$adw] = md5($cf);
}
return db::save($cp, $ab);
}
function refresh_token($cp, $be, $gu = '')
{
$ady = cguid();
$ab = ['id' => $be, 'token' => $ady];
$ak = db::save($cp, $ab);
if ($gu) {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/', $gu);
} else {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/');
}
return $ak;
}
function local_user($adz, $jx, $cp = 'user')
{
return \db::row($cp, [$adz => $jx]);
}
function user_login($app, $adv = 'username', $adw = 'password', $cp = 'user', $adx = 0)
{
$ab = ctx::data();
$ab = select_keys([$adv, $adw], $ab);
$ce = $ab[$adv];
$cf = $ab[$adw];
if (!$ce || !$cf) {
return NULL;
}
$ak = \db::row($cp, ["{$adv}" => $ce]);
if ($ak) {
if ($adx) {
$abi = $ak['salt'];
list($cf, $abi) = hashsalt($cf, $abi);
} else {
$cf = md5($cf);
}
if ($cf == $ak[$adw]) {
return $ak;
}
}
return NULL;
}
function uc_auto_reg_user($ev, $aef)
{
$v = \uc::find_user(['username' => $ev]);
if ($v['code'] != 0) {
$v = uc::reg_user($ev, $aef);
} else {
$v = ['code' => 1, 'data' => []];
}
return $v;
}
function uc_login_data($bi)
{
$ay = uc::user_info($bi);
$ay = $ay['data'];
$fq = [];
$aeg = uc::user_role($bi, 1);
$lu = [];
if ($aeg['code'] == 0) {
$lu = $aeg['data']['roles'];
if ($lu) {
foreach ($lu as $k => $fo) {
$fq[] = $fo['name'];
}
}
}
$ay['roles'] = $fq;
$aeh = uc::user_domain($bi);
$ay['corps'] = array_values($aeh['data']);
return [$bi, $ay, $lu];
}
function uc_user_login($app, $adv = 'username', $adw = 'password')
{
log_time("uc_user_login start");
$ty = $app->getContainer();
$z = $ty->request;
$ab = $z->getParams();
$ab = select_keys([$adv, $adw], $ab);
$ce = $ab[$adv];
$cf = $ab[$adw];
if (!$ce || !$cf) {
return NULL;
}
uc::init();
$v = uc::pwd_login($ce, $cf);
if ($v['code'] != 0) {
ret($v['code'], $v['message']);
}
$bi = $v['data']['access_token'];
return uc_login_data($bi);
}
function cube_user_login($app, $adv = 'username', $adw = 'password')
{
$ty = $app->getContainer();
$z = $ty->request;
$ab = $z->getParams();
$ab = select_keys([$adv, $adw], $ab);
$ce = $ab[$adv];
$cf = $ab[$adw];
if (!$ce || !$cf) {
return NULL;
}
$ab = cube::login($ce, $cf);
$ak = cube::user();
$ak['user']['modules'] = cube::modules()['modules'];
$ak['passport'] = cube::$passport;
$fq = cube::roles()['roles'];
$aei = indexArray($fq, 'id');
$lu = [];
if ($ak['user']['roles']) {
foreach ($ak['user']['roles'] as &$aej) {
$aej['name'] = $aei[$aej['role_id']]['name'];
$aej['title'] = $aei[$aej['role_id']]['title'];
$aej['description'] = $aei[$aej['role_id']]['description'];
$lu[] = $aej['name'];
}
}
$ak['user']['role_list'] = $ak['user']['roles'];
$ak['user']['roles'] = $lu;
return $ak;
}
function check_auth($app)
{
$z = req();
$aek = false;
$ael = cfg::get('public_paths');
$fx = $z->getUri()->getPath();
if ($fx == '/') {
$aek = true;
} else {
foreach ($ael as $bs) {
if (startWith($fx, $bs)) {
$aek = true;
}
}
}
info("check_auth: {$aek} {$fx}");
if (!$aek) {
if (is_weixin()) {
$gv = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $gv);
}
ret(1, 'auth error');
}
}
function extractUserData($aem)
{
return ['githubLogin' => $aem['login'], 'githubName' => $aem['name'], 'githubId' => $aem['id'], 'repos_url' => $aem['repos_url'], 'avatar_url' => $aem['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ak, $aen = false)
{
unset($ak['passwd']);
unset($ak['salt']);
if (!$aen) {
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
return ['token' => $aw, 'userinfo' => $ay];
}
if (!isset($_SERVER['REQUEST_METHOD'])) {
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/pub/psysh';
}
$app = new \Slim\App();
ctx::app($app);
function tpl($bq, $aeo = '.html')
{
$bq = $bq . $aeo;
$aep = cfg::get('tpl_prefix');
$aeq = "{$aep['pc']}/{$bq}";
$aer = "{$aep['mobile']}/{$bq}";
info("tpl: {$aeq} | {$aer}");
return isMobile() ? $aer : $aeq;
}
function req()
{
return ctx::req();
}
function get($bu, $mn = '')
{
$z = req();
$u = $z->getParam($bu, $mn);
if ($u == $mn) {
$aes = ctx::gets();
if (isset($aes[$bu])) {
return $aes[$bu];
}
}
return $u;
}
function post($bu, $mn = '')
{
$z = req();
return $z->getParam($bu, $mn);
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
$fx = $z->getUri()->getPath();
if (!startWith($fx, '/')) {
$fx = '/' . $fx;
}
return $fx;
}
function host_str($sv)
{
$aet = '';
if (isset($_SERVER['HTTP_HOST'])) {
$aet = $_SERVER['HTTP_HOST'];
}
return " [ {$aet} ] " . $sv;
}
function debug($sv)
{
$m = \cfg::get('app');
if (isset($m['log_level']) && $m['log_level'] == 100) {
if (ctx::logger()) {
$sv = format_log_str($sv, getCallerStr(3));
ctx::logger()->debug(host_str($sv));
}
}
}
function warn($sv)
{
if (ctx::logger()) {
$sv = format_log_str($sv, getCallerStr(3));
ctx::logger()->warn(host_str($sv));
}
}
function info($sv)
{
if (ctx::logger()) {
$sv = format_log_str($sv, getCallerStr(3));
ctx::logger()->info(host_str($sv));
}
}
function format_log_str($sv, $aeu = '')
{
if (is_array($sv)) {
$sv = json_encode($sv);
}
return "{$sv} [ ::{$aeu} ]";
}
function ck_owner($ds)
{
$be = ctx::uid();
$ko = $ds['uid'];
debug("ck_owner: {$be} {$ko}");
return $be == $ko;
}
function _err($bu)
{
return cfg::get($bu, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bw = '', $abm = 0)
{
global $__log_time__, $__log_begin_time__;
list($xz, $yz) = explode(" ", microtime());
$aev = (double) $xz + (double) $yz;
if (!$__log_time__) {
$__log_begin_time__ = $aev;
$__log_time__ = $aev;
$bs = uripath();
debug("usetime: --- {$bs} ---");
return $aev;
}
if ($abm && $abm == 'begin') {
$aew = $__log_begin_time__;
} else {
$aew = $abm ? $abm : $__log_time__;
}
$abo = $aev - $aew;
$abo *= 1000;
debug("usetime: ---  {$abo} {$bw}  ---");
$__log_time__ = $aev;
return $aev;
}
use core\Service as ms;
$p = $app->getContainer();
$p['view'] = function ($ty) {
$br = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$br->addExtension(new \Slim\Views\TwigExtension($ty['router'], $ty['request']->getUri()));
return $br;
};
$p['logger'] = function ($ty) {
if (is_docker_env()) {
$aex = '/ws/log/app.log';
} else {
$aey = cfg::get('logdir');
if ($aey) {
$aex = $aey . '/app.log';
} else {
$aex = __DIR__ . '/../app.log';
}
}
$aez = ['name' => '', 'path' => $aex];
$afg = new \Monolog\Logger($aez['name']);
$afg->pushProcessor(new \Monolog\Processor\UidProcessor());
$afh = \cfg::get('app');
$nw = isset($afh['log_level']) ? $afh['log_level'] : '';
if (!$nw) {
$nw = \Monolog\Logger::INFO;
}
$afg->pushHandler(new \Monolog\Handler\StreamHandler($aez['path'], $nw));
return $afg;
};
log_time();
unset($app->getContainer()['phpErrorHandler']);
unset($app->getContainer()['errorHandler']);
$p['notFoundHandler'] = function ($ty) {
if (!\ctx::isFoundRoute()) {
return function ($fu, $fv) use($ty) {
return $ty['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($fu, $fv) use($ty) {
return $ty['response'];
};
};
$p['ms'] = function ($ty) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($jz, $l, array $az) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$afi = ROOT_PATH . '/routes';
if (folder_exist($afi)) {
$q = dir::scan($afi, ['type' => 'file']);
foreach ($q as $r) {
if (basename($r) != 'routes.php' && !endWith($r, '.DS_Store')) {
require_once $r;
}
}
}
$afj = cfg::get('opt_route_list');
if ($afj) {
foreach ($afj as $aj) {
info("def route {$aj}");
$app->options($aj, function () {
ret([]);
});
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
if (cfg::get('enable_blockly')) {
$afk = cache('blockly_routes_key', function () {
return db::all('sys_blockly', ['AND' => ['code_type' => 'route']]);
}, 86400, 1);
foreach ($afk as $afl) {
$afm = get('nb');
if ($afm != 1) {
@eval($afl['phpcode']);
}
}
}
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $bu, $dx = array())
{
$app->options("/hot/{$bu}", function () {
ret([]);
});
$app->options("/hot/{$bu}/{id}", function () {
ret([]);
});
$app->get("/hot/{$bu}", function () use($dx, $bu) {
$abu = $dx['objname'];
$afn = $bu;
$cx = rest::getList($afn);
$acy = isset($dx['cols_map']) ? $dx['cols_map'] : [];
list($jw, $adg, $adh, $adi, $dj) = getMetaData($abu, $acy);
$adi[0] = 10;
$v['data'] = ['meta' => $jw, 'list' => $cx['data'], 'colHeaders' => $adg, 'colWidths' => $adi, 'cols' => $dj];
ret($v);
});
$app->get("/hot/{$bu}/param", function () use($dx, $bu) {
$abu = $dx['objname'];
$afn = $bu;
$cx = rest::getList($afn);
list($adg, $afo, $adh, $adi, $dj) = getHotColMap1($afn);
$jw = ['objname' => $abu];
$adi[0] = 10;
$v['data'] = ['meta' => $jw, 'list' => [], 'colHeaders' => $adg, 'colHeaderDatas' => $adh, 'colHeaderGroupDatas' => $afo, 'colWidths' => $adi, 'cols' => $dj];
ret($v);
});
$app->post("/hot/{$bu}", function () use($dx, $bu) {
$afn = $bu;
$cx = rest::postData($afn);
ret($cx);
});
$app->put("/hot/{$bu}/{id}", function ($z, $bo, $ms) use($dx, $bu) {
$afn = $bu;
$ab = ctx::data();
if (isset($ab['trans-word']) && isset($ab[$ab['trans-from']])) {
$afp = $ab['trans-from'];
$afq = $ab['trans-to'];
$u = util\Pinyin::get($ab[$afp]);
$ab[$afq] = $u;
}
ctx::data($ab);
$cx = rest::putData($afn, $ms['id']);
ret($cx);
});
}
function getHotColMap1($afn)
{
$afr = $afn . '_param';
$afs = $afn . '_opt';
$aft = $afn . '_opt_ext';
ctx::pagesize(50);
$cx = rest::getList($afr);
$afu = getKeyValues($cx['data'], 'id');
$az = indexArray($cx['data'], 'id');
$cg = db::all($afs, ['AND' => ['pid' => $afu]]);
$cg = indexArray($cg, 'id');
$afu = array_keys($cg);
$afv = db::all($aft, ['AND' => ['pid' => $afu]]);
$afv = groupArray($afv, 'pid');
$adg = [];
$adh = [];
$afo = [];
$adi = [];
$dj = [];
foreach ($az as $k => $afw) {
$adg[] = $afw['label'];
$afo[$afw['name']] = $afw['group_name'] ? $afw['group_name'] : $afw['label'];
$adh[$afw['name']] = $afw['label'];
$adi[] = $afw['width'];
$dj[$afw['name']] = ['data' => $afw['name'], 'renderer' => 'html'];
}
foreach ($afv as $k => $eq) {
$afx = '';
$adn = 0;
$afy = $cg[$k];
$afz = $afy['pid'];
$afw = $az[$afz];
$agh = $afw['label'];
$afx = $afw['name'];
$agi = $afw['type'];
if ($adn) {
}
if ($afx) {
$ct = ['data' => $afx, 'type' => 'autocomplete', 'strict' => false, 'source' => array_values(getKeyValues($eq, 'option'))];
if ($agi == 'select2') {
$ct['editor'] = 'select2';
$agj = [];
foreach ($eq as $agk) {
$ds['id'] = $agk['id'];
$ds['text'] = $agk['option'];
$agj[] = $ds;
}
$ct['select2Options'] = ['data' => $agj, 'dropdownAutoWidth' => true, 'width' => 'resolve'];
unset($ct['type']);
}
$dj[$afx] = $ct;
}
}
$dj = array_values($dj);
return [$adg, $afo, $adh, $adi, $dj];
$ab = ['rows' => $cx, 'pids' => $afu, 'props' => $agl, 'opts' => $cg, 'cols_map' => $acy];
$acy = [];
return $acy;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $bu, $dx = array())
{
$afn = $bu;
$agm = "{$bu}_ext";
$app->get("/hot/{$bu}", function () use($afn, $agm) {
$kr = get('oid');
$adn = get('pid');
$cr = "select * from `{$afn}` pp join `{$agm}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$kr} and pp.pid={$adn}";
$cx = db::query($cr);
$ab = groupArray($cx, 'name');
$adg = ['Id', 'Oid', 'RowNum'];
$adi = [5, 5, 5];
$dj = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ai = [];
foreach ($ab as $bx => $by) {
$adg[] = $by[0]['label'];
$adi[] = $by[0]['col_width'];
$dj[] = ['data' => $bx, 'renderer' => 'html'];
$agn = [];
foreach ($by as $k => $ds) {
$ai[$ds['_rownum']][$bx] = $ds['option'];
if ($bx == 'value') {
if (!isset($ai[$ds['_rownum']]['id'])) {
$ai[$ds['_rownum']]['id'] = $ds['id'];
$ai[$ds['_rownum']]['oid'] = $kr;
$ai[$ds['_rownum']]['_rownum'] = $ds['_rownum'];
}
}
}
}
$ai = array_values($ai);
$v['data'] = ['list' => $ai, 'colHeaders' => $adg, 'colWidths' => $adi, 'cols' => $dj];
ret($v);
});
$app->get("/hot/{$bu}_addprop", function () use($afn, $agm) {
$kr = get('oid');
$adn = get('pid');
$ago = get('propname');
if ($ago != 'value' && !checkOptPropVal($kr, $adn, 'value', $afn, $agm)) {
addOptProp($kr, $adn, 'value', $afn, $agm);
}
if (!checkOptPropVal($kr, $adn, $ago, $afn, $agm)) {
addOptProp($kr, $adn, $ago, $afn, $agm);
}
ret([11]);
});
$app->options("/hot/{$bu}", function () {
ret([]);
});
$app->options("/hot/{$bu}/{id}", function () {
ret([]);
});
$app->post("/hot/{$bu}", function () use($afn, $agm) {
$ab = ctx::data();
$adn = $ab['pid'];
$kr = $ab['oid'];
$agp = getArg($ab, '_rownum');
$agq = db::row($afn, ['AND' => ['oid' => $kr, 'pid' => $adn, 'name' => 'value']]);
if (!$agq) {
addOptProp($kr, $adn, 'value', $afn, $agm);
}
$agr = $agq['id'];
$ags = db::obj()->max($agm, '_rownum', ['pid' => $agr]);
$ab = ['oid' => $kr, 'pid' => $agr, '_rownum' => $ags + 1];
db::save($agm, $ab);
$v = ['oid' => $kr, '_rownum' => $agp, 'prop' => $agq, 'maxrow' => $ags];
ret($v);
});
$app->put("/hot/{$bu}/{id}", function ($z, $bo, $ms) use($agm, $afn) {
$ab = ctx::data();
$adn = $ab['pid'];
$kr = $ab['oid'];
$agp = $ab['_rownum'];
$agp = getArg($ab, '_rownum');
$aw = $ab['token'];
$be = $ab['uid'];
$ds = dissoc($ab, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($ds);
$k = key($ds);
$u = $ds[$k];
$agq = db::row($afn, ['AND' => ['pid' => $adn, 'oid' => $kr, 'name' => $k]]);
info("{$adn} {$kr} {$k}");
$agr = $agq['id'];
$agt = db::obj()->has($agm, ['AND' => ['pid' => $agr, '_rownum' => $agp]]);
if ($agt) {
debug("has cell ...");
$cr = "update {$agm} set `option`='{$u}' where _rownum={$agp} and pid={$agr}";
debug($cr);
db::exec($cr);
} else {
debug("has no cell ...");
$ab = ['oid' => $kr, 'pid' => $agr, '_rownum' => $agp, 'option' => $u];
db::save($agm, $ab);
}
$v = ['item' => $ds, 'oid' => $kr, '_rownum' => $agp, 'key' => $k, 'val' => $u, 'prop' => $agq, 'sql' => $cr];
ret($v);
});
}
function checkOptPropVal($kr, $adn, $bu, $afn, $agm)
{
return db::obj()->has($afn, ['AND' => ['name' => $bu, 'oid' => $kr, 'pid' => $adn]]);
}
function addOptProp($kr, $adn, $ago, $afn, $agm)
{
$bu = Pinyin::get($ago);
$ab = ['oid' => $kr, 'pid' => $adn, 'label' => $ago, 'name' => $bu];
$agq = db::save($afn, $ab);
$ab = ['_rownum' => 1, 'oid' => $kr, 'pid' => $agq['id']];
db::save($agm, $ab);
return $agq;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$agu = \cfg::load('mid');
if ($agu) {
foreach ($agu as $bx => $m) {
$agv = "\\{$bx}";
debug("load mid: {$agv}");
$app->add(new $agv());
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