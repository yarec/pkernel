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
public static function openid_login($ch, $ci = 'wechat')
{
self::client();
$cg = ['args' => ['client_id' => self::$cid, 'client_secret' => self::$cst, 'openid' => $ch, 'bind_type' => $ci], "resp" => ["access_token", "token_type", 'expires_in', 'refresh_token']];
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
function fixfn($cj)
{
foreach ($cj as $ck) {
if (!function_exists($ck)) {
eval("function {$ck}(){}");
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
$cj = array('debug');
fixfn($cj);
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
public static function init($m, $cl = true)
{
self::init_db($m, $cl);
}
public static function conns()
{
$cm['_db'] = self::queryRow('select user() as user, database() as dbname');
self::use_master_db();
$cm['_db_master'] = self::queryRow('select user() as user, database() as dbname');
self::use_default_db();
$cm['_db_default'] = self::queryRow('select user() as user, database() as dbname');
return $cm;
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
public static function init_db($m, $cl = true)
{
self::$_dbc = self::get_db_cfg($m);
$cn = self::$_dbc['database_name'];
self::$_dbc_list[$cn] = self::$_dbc;
self::$_db_list[$cn] = self::new_db(self::$_dbc);
if ($cl) {
self::use_db($cn);
}
}
public static function use_db($cn)
{
self::$_db = self::$_db_list[$cn];
self::$_dbc = self::$_dbc_list[$cn];
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
public static function switch_dbc($co)
{
$cp = ms('master')->get(['path' => '/admin/corpins', 'data' => ['corpid' => $co]]);
$cq = $cp->json();
$cq = getArg($cq, 'data', []);
self::$_dbc = $cq;
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
$co = getArg($ak, 'corpid');
if ($co) {
self::switch_dbc($co);
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
public static function desc_sql($cr)
{
if (self::db_type() == 'mysql') {
return "desc `{$cr}`";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$cr}'";
} else {
return '';
}
}
}
public static function table_cols($bu)
{
$cs = self::$tbl_desc;
if (!isset($cs[$bu])) {
$ct = self::desc_sql($bu);
if ($ct) {
$cs[$bu] = self::query($ct);
self::$tbl_desc = $cs;
debug("---------------- cache not found : {$bu}");
} else {
debug("empty desc_sql for: {$bu}");
}
}
if (!isset($cs[$bu])) {
return array();
} else {
return self::$tbl_desc[$bu];
}
}
public static function col_array($bu)
{
$cu = function ($by) use($bu) {
return $bu . '.' . $by;
};
return getKeyValues(self::table_cols($bu), 'Field', $cu);
}
public static function valid_table_col($bu, $cv)
{
$cw = self::table_cols($bu);
foreach ($cw as $cx) {
if ($cx['Field'] == $cv) {
$c = $cx['Type'];
return is_string_column($cx['Type']);
}
}
return false;
}
public static function tbl_data($bu, $ab)
{
$cw = self::table_cols($bu);
$v = [];
foreach ($cw as $cx) {
$cy = $cx['Field'];
if (isset($ab[$cy])) {
$v[$cy] = $ab[$cy];
}
}
return $v;
}
public static function test()
{
$ct = "select * from tags limit 10";
$cz = self::obj()->query($ct)->fetchAll(\PDO::FETCH_ASSOC);
var_dump($cz);
}
public static function has_st($bu, $de)
{
$df = '_st';
return isset($de[$df]) || isset($de[$bu . '.' . $df]);
}
public static function getWhere($bu, $dg)
{
$df = '_st';
if (!self::valid_table_col($bu, $df)) {
return $dg;
}
$df = $bu . '._st';
if (is_array($dg)) {
$dh = array_keys($dg);
$di = preg_grep("/^AND\\s*#?\$/i", $dh);
$dj = preg_grep("/^OR\\s*#?\$/i", $dh);
$dk = array_diff_key($dg, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
$de = [];
if ($dk != array()) {
$de = $dk;
if (!self::has_st($bu, $de)) {
$dg[$df] = 1;
$dg = ['AND' => $dg];
}
}
if (!empty($di)) {
$l = array_values($di);
$de = $dg[$l[0]];
if (!self::has_st($bu, $de)) {
$dg[$l[0]][$df] = 1;
}
}
if (!empty($dj)) {
$l = array_values($dj);
$de = $dg[$l[0]];
if (!self::has_st($bu, $de)) {
$dg[$l[0]][$df] = 1;
}
}
if (!isset($dg['AND']) && !self::has_st($bu, $de)) {
$dg['AND'][$df] = 1;
}
}
return $dg;
}
public static function all_sql($bu, $dg = array(), $dl = '*', $dm = null)
{
$dn = [];
if ($dm) {
$ct = self::obj()->selectContext($bu, $dn, $dm, $dl, $dg);
} else {
$ct = self::obj()->selectContext($bu, $dn, $dl, $dg);
}
return $ct;
}
public static function all($bu, $dg = array(), $dl = '*', $dm = null)
{
$dg = self::getWhere($bu, $dg);
info($dg);
if ($dm) {
$cz = self::obj()->select($bu, $dm, $dl, $dg);
} else {
$cz = self::obj()->select($bu, $dl, $dg);
}
return $cz;
}
public static function count($bu, $dg = array('_st' => 1))
{
$dg = self::getWhere($bu, $dg);
return self::obj()->count($bu, $dg);
}
public static function row_sql($bu, $dg = array(), $dl = '*', $dm = '')
{
return self::row($bu, $dg, $dl, $dm, true);
}
public static function row($bu, $dg = array(), $dl = '*', $dm = '', $do = null)
{
$dg = self::getWhere($bu, $dg);
if (!isset($dg['LIMIT'])) {
$dg['LIMIT'] = 1;
}
if ($dm) {
if ($do) {
return self::obj()->selectContext($bu, $dm, $dl, $dg);
}
$cz = self::obj()->select($bu, $dm, $dl, $dg);
} else {
if ($do) {
return self::obj()->selectContext($bu, $dl, $dg);
}
$cz = self::obj()->select($bu, $dl, $dg);
}
if ($cz) {
return $cz[0];
} else {
return null;
}
}
public static function one($bu, $dg = array(), $dl = '*', $dm = '')
{
$dp = self::row($bu, $dg, $dl, $dm);
$dq = '';
if ($dp) {
$dr = array_keys($dp);
$dq = $dp[$dr[0]];
}
return $dq;
}
public static function parseUk($bu, $ds, $ab)
{
$dt = true;
info("uk: {$ds}, " . json_encode($ab));
if (is_array($ds)) {
foreach ($ds as $du) {
if (!isset($ab[$du])) {
$dt = false;
} else {
$dv[$du] = $ab[$du];
}
}
} else {
if (!isset($ab[$ds])) {
$dt = false;
} else {
$dv = [$ds => $ab[$ds]];
}
}
$dw = false;
if ($dt) {
info("has uk {$dt}");
info("where: " . json_encode($dv));
if (!self::obj()->has($bu, ['AND' => $dv])) {
$dw = true;
}
} else {
$dw = true;
}
return [$dv, $dw];
}
public static function save($bu, $ab, $ds = 'id')
{
list($dv, $dw) = self::parseUk($bu, $ds, $ab);
info("isInsert: {$dw}, {$bu} {$ds} " . json_encode($ab));
if ($dw) {
debug("insert {$bu} : " . json_encode($ab));
$dx = self::obj()->insert($bu, $ab);
$ab['id'] = self::obj()->id();
} else {
debug("update {$bu} " . json_encode($dv));
$dx = self::obj()->update($bu, $ab, ['AND' => $dv]);
}
if ($dx->errorCode() !== '00000') {
info($dx->errorInfo());
}
return $ab;
}
public static function update($bu, $ab, $dg)
{
self::obj()->update($bu, $ab, $dg);
}
public static function exec($ct)
{
return self::obj()->exec($ct);
}
public static function query($ct)
{
return self::obj()->query($ct)->fetchAll(\PDO::FETCH_ASSOC);
}
public static function queryRow($ct)
{
$cz = self::query($ct);
if ($cz) {
return $cz[0];
} else {
return null;
}
}
public static function queryOne($ct)
{
$dp = self::queryRow($ct);
return self::oneVal($dp);
}
public static function oneVal($dp)
{
$dq = '';
if ($dp) {
$dr = array_keys($dp);
$dq = $dp[$dr[0]];
}
return $dq;
}
public static function updateBatch($bu, $ab, $ds = 'id')
{
$dy = $bu;
if (!is_array($ab) || empty($dy)) {
return FALSE;
}
$ct = "UPDATE `{$dy}` SET";
foreach ($ab as $bv => $dp) {
foreach ($dp as $k => $u) {
$dz[$k][] = "WHEN {$bv} THEN {$u}";
}
}
foreach ($dz as $k => $u) {
$ct .= ' `' . trim($k, '`') . '`=CASE ' . $ds . ' ' . join(' ', $u) . ' END,';
}
$ct = trim($ct, ',');
$ct .= ' WHERE ' . $ds . ' IN(' . join(',', array_keys($ab)) . ')';
return self::query($ct);
}
}
class fcache
{
const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
const CLEAR_ALL_KEY = 'CLEAR_ALL';
static $_instance = null;
protected $_options = array('cache_dir' => './cache', 'file_locking' => true, 'file_name_prefix' => 'cache', 'cache_file_umask' => 0777, 'file_life' => 100000);
static function &getInstance($ef = array())
{
if (self::$_instance === null) {
self::$_instance = new self($ef);
}
return self::$_instance;
}
static function &setOptions($ef = array())
{
return self::getInstance($ef);
}
private function __construct($ef = array())
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
$eg =& self::getInstance();
if (!is_dir($l)) {
exit('file_cache: ' . $l . ' 不是一个有效路径 ');
}
if (!is_writable($l)) {
exit('file_cache: 路径 "' . $l . '" 不可写');
}
$l = rtrim($this->_options['cache_dir'], '/') . '/';
$eg->_options['cache_dir'] = $l;
}
static function save($ab, $bv = null, $eh = null)
{
$eg =& self::getInstance();
if (!$bv) {
if ($eg->_id) {
$bv = $eg->_id;
} else {
exit('file_cache:save() id 不能为空!');
}
}
$ei = time();
if ($eh) {
$ab[self::FILE_LIFE_KEY] = $ei + $eh;
} elseif ($eh != 0) {
$ab[self::FILE_LIFE_KEY] = $ei + $eg->_options['file_life'];
}
$r = $eg->_file($bv);
$ab = "\n" . " // mktime: " . $ei . "\n" . " return " . var_export($ab, true) . "\n?>";
$cp = $eg->_filePutContents($r, $ab);
return $cp;
}
static function load($bv)
{
$eg =& self::getInstance();
$ei = time();
if (!$eg->test($bv)) {
return false;
}
$ej = $eg->_file(self::CLEAR_ALL_KEY);
$r = $eg->_file($bv);
if (is_file($ej) && filemtime($ej) > filemtime($r)) {
return false;
}
$ab = $eg->_fileGetContents($r);
if (empty($ab[self::FILE_LIFE_KEY]) || $ei < $ab[self::FILE_LIFE_KEY]) {
unset($ab[self::FILE_LIFE_KEY]);
return $ab;
}
return false;
}
protected function _filePutContents($r, $ek)
{
$eg =& self::getInstance();
$el = false;
$em = @fopen($r, 'ab+');
if ($em) {
if ($eg->_options['file_locking']) {
@flock($em, LOCK_EX);
}
fseek($em, 0);
ftruncate($em, 0);
$en = @fwrite($em, $ek);
if (!($en === false)) {
$el = true;
}
@fclose($em);
}
@chmod($r, $eg->_options['cache_file_umask']);
return $el;
}
protected function _file($bv)
{
$eg =& self::getInstance();
$eo = $eg->_idToFileName($bv);
return $eg->_options['cache_dir'] . $eo;
}
protected function _idToFileName($bv)
{
$eg =& self::getInstance();
$eg->_id = $bv;
$x = $eg->_options['file_name_prefix'];
$el = $x . '---' . $bv;
return $el;
}
static function test($bv)
{
$eg =& self::getInstance();
$r = $eg->_file($bv);
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
$eg =& self::getInstance();
$eg->save('CLEAR_ALL', self::CLEAR_ALL_KEY);
}
static function del($bv)
{
$eg =& self::getInstance();
if (!$eg->test($bv)) {
return false;
}
$r = $eg->_file($bv);
return unlink($r);
}
}
class mdb
{
private static $_client;
private static $_db;
private static $_ins;
public static function obj($cn = 'myapp_dev')
{
if (!self::$_client) {
self::$_client = new \Sokil\Mongo\Client();
}
if (!self::$_db) {
self::$_db = self::$_client->{$cn};
}
return self::$_db;
}
public static function test()
{
$be = 1;
$ep = self::obj()->blogs;
$eq = $ep->find()->findAll();
$ab = object2array($eq);
$er = 1;
foreach ($ab as $bx => $es) {
unset($es['_id']);
unset($es['tid']);
unset($es['tags']);
if (isset($es['_intm'])) {
$es['_intm'] = date('Y-m-d H:i:s', $es['_intm']['sec']);
}
if (isset($es['_uptm'])) {
$es['_uptm'] = date('Y-m-d H:i:s', $es['_uptm']['sec']);
}
$es['uid'] = $be;
$v = db::save('blogs', $es);
$er++;
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
public static function init($et = array())
{
$m = cfg::get('oauth', 'oauth')['uc'];
$m['host'] = env('UC_HOST', $m['host']);
$m['clientId'] = env('UC_CLIENT_ID', $m['clientId']);
$m['clientSecret'] = env('UC_CLIENT_SECRET', $m['clientSecret']);
$m['redirectUri'] = env('UC_REDIRECT_URI', $m['redirectUri']);
$m['username'] = env('UC_USERNAME', $m['username']);
$m['passwd'] = env('UC_PASSWD', $m['passwd']);
self::$oauth_cfg = $m;
if (isset($et['host'])) {
self::$UC_HOST = $et['host'];
}
}
public static function makeUrl($bs, $az = '')
{
if (!self::$oauth_cfg) {
self::init();
}
return self::$oauth_cfg['host'] . $bs . ($az ? '?' . $az : '');
}
public static function pwd_login($eu = null, $ev = null, $ew = null, $ex = null)
{
$ey = $eu ? $eu : self::$oauth_cfg['username'];
$cf = $ev ? $ev : self::$oauth_cfg['passwd'];
$ez = $ew ? $ew : self::$oauth_cfg['clientId'];
$fg = $ex ? $ex : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $ez, 'client_secret' => $fg, 'grant_type' => 'password', 'username' => $ey, 'password' => $cf];
$fh = self::makeUrl(self::API['accessToken']);
$fi = curl($fh, 10, 30, $ab);
$v = json_decode($fi, true);
self::_set_pwd_user($v);
return $v;
}
public static function get_admin_token($fj = array())
{
if (isset($fj['access_token'])) {
$bi = $fj['access_token'];
} else {
$v = self::pwd_login();
$bi = $v['data']['access_token'];
}
return $bi;
}
public static function id_login($bv, $ew = null, $ex = null, $cg = array())
{
$ez = $ew ? $ew : self::$oauth_cfg['clientId'];
$fg = $ex ? $ex : self::$oauth_cfg['clientSecret'];
$bi = self::get_admin_token($cg);
$ab = ['client_id' => $ez, 'client_secret' => $fg, 'grant_type' => 'id', 'access_token' => $bi, 'id' => $bv];
$fh = self::makeUrl(self::API['userAccessToken']);
$fi = curl($fh, 10, 30, $ab);
$v = json_decode($fi, true);
self::_set_id_user($v);
return $v;
}
public static function authurl($bk, $fk, $bi)
{
$fl = self::$oauth_cfg['host'] . "/api/sso/redirect?access_token={$bi}&app_id={$bk}&domain_id={$fk}";
return $fl;
}
public static function code_login($fm, $fn = null, $ew = null, $ex = null)
{
$fo = $fn ? $fn : self::$oauth_cfg['redirectUri'];
$ez = $ew ? $ew : self::$oauth_cfg['clientId'];
$fg = $ex ? $ex : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $ez, 'client_secret' => $fg, 'grant_type' => 'authorization_code', 'redirect_uri' => $fo, 'code' => $fm];
$fh = self::makeUrl(self::API['accessToken']);
$fi = curl($fh, 10, 30, $ab);
$v = json_decode($fi, true);
self::_set_code_user($v);
return $v;
}
public static function user_info($bi)
{
$fh = self::makeUrl(self::API['user'], 'access_token=' . $bi);
$fi = curl($fh);
$v = json_decode($fi, true);
self::_set_user_info($v);
return $v;
}
public static function reg_user($ey, $ev = '123456', $cg = array())
{
$bi = self::get_admin_token($cg);
$ab = ['username' => $ey, 'password' => $ev, 'access_token' => $bi];
$fh = self::makeUrl(self::API['user']);
$fi = curl($fh, 10, 30, $ab);
$fp = json_decode($fi, true);
return $fp;
}
public static function register_user($ey, $ev = '123456')
{
return self::reg_user($ey, $ev);
}
public static function find_user($fj = array())
{
$bi = self::get_admin_token($fj);
$az = 'access_token=' . $bi;
if (isset($fj['username'])) {
$az .= '&username=' . $fj['username'];
}
if (isset($fj['phone'])) {
$az .= '&phone=' . $fj['phone'];
}
$fh = self::makeUrl(self::API['finduser'], $az);
$fi = curl($fh, 10, 30);
$fp = json_decode($fi, true);
return $fp;
}
public static function edit_user($bi, $ab = array())
{
$fh = self::makeUrl(self::API['user']);
$ab['access_token'] = $bi;
$cd = new \GuzzleHttp\Client();
$cp = $cd->request('PUT', $fh, ['form_params' => $ab, 'headers' => ['X-Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded']]);
$fi = $cp->getBody();
return json_decode($fi, true);
}
public static function set_user_role($bi, $fk, $fq, $fr = 'guest')
{
$ab = ['access_token' => $bi, 'domain_id' => $fk, 'user_id' => $fq, 'role_name' => $fr];
$fh = self::makeUrl(self::API['userRole']);
$fi = curl($fh, 10, 30, $ab);
return json_decode($fi, true);
}
public static function user_role($bi, $fk)
{
$ab = ['access_token' => $bi, 'domain_id' => $fk];
$fh = self::makeUrl(self::API['userRole']);
$fh = "{$fh}?access_token={$bi}&domain_id={$fk}";
$fi = curl($fh, 10, 30);
$v = json_decode($fi, true);
self::_set_user_role($v);
return $v;
}
public static function has_role($fs)
{
if (self::$user_role && isset(self::$user_role['roles'])) {
$ft = self::$user_role['roles'];
foreach ($ft as $k => $fr) {
if ($fr['name'] == $fs) {
return true;
}
}
}
return false;
}
public static function create_domain($fu, $fv, $cg = array())
{
$bi = self::get_admin_token($cg);
$ab = ['access_token' => $bi, 'domain_name' => $fu, 'description' => $fv];
$fh = self::makeUrl(self::API['createDomain']);
$fi = curl($fh, 10, 30, $ab);
$v = json_decode($fi, true);
return $v;
}
public static function user_domain($bi)
{
$ab = ['access_token' => $bi];
$fh = self::makeUrl(self::API['userdomain']);
$fh = "{$fh}?access_token={$bi}";
$fi = curl($fh, 10, 30);
$v = json_decode($fi, true);
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
$fw = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$by->rules($fw);
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
public function __invoke($fx, $fy, $fz)
{
log_time("Twig Begin");
$fy = $fz($fx, $fy);
$gh = uripath($fx);
debug(">>>>>> TwigMid START : {$gh}  <<<<<<");
if ($gi = $this->getRoutePath($fx)) {
$br = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($br->data);
}
$gj = rtrim($gi, '/');
if ($gj == '/' || !$gj) {
$gj = 'index';
}
$bq = $gj;
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
$ab['authuser'] = \ctx::user();
$ab['uri'] = \ctx::uri();
$ab['t'] = time();
$ab['domain'] = \cfg::get('wechat_callback_domain');
$ab['gdata'] = \ctx::global_view_data();
debug("<<<<<< TwigMid END : {$gh} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $br->render($fy, tpl($bq), $ab);
} else {
return $fy;
}
}
public function getRoutePath($fx)
{
$gk = \ctx::router()->dispatch($fx);
if ($gk[0] === Dispatcher::FOUND) {
$aj = \ctx::router()->lookupRoute($gk[1]);
$gl = $aj->getPattern();
$gm = new StdParser();
$gn = $gm->parse($gl);
foreach ($gn as $go) {
foreach ($go as $du) {
if (is_string($du)) {
return $du;
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
private $isAjax = true;
public function __invoke($fx, $fy, $fz)
{
log_time("AuthMid Begin");
$gh = uripath($fx);
debug(">>>>>> AuthMid START : {$gh}  <<<<<<");
\ctx::init($fx);
$this->check_auth($fx, $fy);
debug("<<<<<< AuthMid END : {$gh} >>>>>");
log_time("AuthMid END");
$fy = $fz($fx, $fy);
return $fy;
}
public function isAjax($bs = '')
{
$gp = \cfg::get('route_type');
if ($gp) {
if ($gp['default'] == 'web') {
$this->isAjax = false;
}
if (isset($gp['web'])) {
}
if (isset($gp['api'])) {
}
}
if ($bs) {
if (startWith($bs, '/rest')) {
$this->isAjax = true;
}
}
return $this->isAjax;
}
public function check_auth($z, $bo)
{
list($gq, $ak, $gr) = $this->auth_cfg();
$gh = uripath($z);
$this->isAjax($gh);
if ($gh == '/') {
return true;
}
$gs = $this->check_list($gq, $gh);
if ($gs) {
$this->check_admin();
}
$gt = $this->check_list($ak, $gh);
if ($gt) {
$this->check_user();
}
$gu = $this->check_list($gr, $gh);
if (!$gu) {
$this->check_user();
}
info("check_auth: {$gh} admin:[{$gs}] user:[{$gt}] pub:[{$gu}]");
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
public function auth_error($gv = 1)
{
$gw = is_weixin();
$gx = isMobile();
$gy = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$gv}, is_weixin: {$gw} , is_mobile: {$gx}");
$gz = $_SERVER['REQUEST_URI'];
if ($gw) {
header("Location: {$gy}/auth/wechat?_r={$gz}");
exit;
}
if ($gx) {
header("Location: {$gy}/auth/openwechat?_r={$gz}");
exit;
}
if ($this->isAjax()) {
ret($gv, 'auth error');
} else {
header('Location: /?_r=' . $gz);
exit;
}
}
public function auth_cfg()
{
$hi = \cfg::get('auth');
return [$hi['admin'], $hi['user'], $hi['public']];
}
public function check_list($ai, $gh)
{
foreach ($ai as $bs) {
if (startWith($gh, $bs)) {
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
public function __invoke($fx, $fy, $fz)
{
$this->init($fx, $fy, $fz);
log_time("{$this->classname} Begin");
$this->path_info = uripath($fx);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($fx, $fy);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$fy = $fz($fx, $fy);
return $fy;
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
public function handlePathArray($hj, $z, $bo)
{
foreach ($hj as $bs => $hk) {
if (startWith($this->path_info, $bs)) {
debug("{$this->path_info} match {$bs} {$hk}");
$this->{$hk}($z, $bo);
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
public function __invoke($fx, $fy, $fz)
{
log_time("RestMid Begin");
$this->path_info = uripath($fx);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($fx)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($fx)) {
$this->apiDoc($fx);
} else {
$this->handelRest($fx);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$fy = $fz($fx, $fy);
return $fy;
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
$hk = $z->getMethod();
info(" method: {$hk}, name: {$bu}, id: {$bv}");
$hl = "handle{$hk}";
$this->{$hl}($z, $bu, $bv);
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
$hm = \cfg::get('rest_maps', 'rest.yml');
if (isset($hm[$bu])) {
$m = $hm[$bu][$c];
if ($m) {
$hn = $m['xmap'];
if ($hn) {
$ab = \ctx::data();
foreach ($hn as $bx => $by) {
unset($ab[$by]);
}
\ctx::data($ab);
}
}
}
}
public function apiDoc($z)
{
$ho = rd::genApi();
echo $ho;
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
public static function whereStr($dg, $bu)
{
$v = '';
foreach ($dg as $bx => $by) {
$gl = '/(.*)\\{(.*)\\}/i';
$bw = preg_match($gl, $bx, $hp);
$hq = '=';
if ($hp) {
$hr = $hp[1];
$hq = $hp[2];
} else {
$hr = $bx;
}
if ($hs = db::valid_table_col($bu, $hr)) {
if ($hs == 2) {
if ($hq == 'in') {
if (is_array($by)) {
$by = implode("','", $by);
}
$v .= " and t1.{$hr} {$hq} ('{$by}')";
} else {
$v .= " and t1.{$hr}{$hq}'{$by}'";
}
} else {
if ($hq == 'in') {
if (is_array($by)) {
$by = implode(',', $by);
}
$v .= " and t1.{$hr} {$hq} ({$by})";
} else {
$v .= " and t1.{$hr}{$hq}{$by}";
}
}
} else {
}
info("[{$bu}] [{$hr}] [{$hs}] {$v}");
}
return $v;
}
public static function getSqlFrom($bu, $ht, $be, $hu, $hv, $cg = array())
{
$hw = isset($_GET['tags']) ? 1 : isset($cg['tags']) ? 1 : 0;
$hx = isset($_GET['isar']) ? 1 : 0;
$hy = RestHelper::get_rest_xwh_tags_list();
if ($hy && in_array($bu, $hy)) {
$hw = 0;
}
$hz = isset($cg['force_ar']) || RestHelper::isAdmin() && $hx ? "1=1" : "t1.uid={$be}";
if ($hw) {
$ij = isset($_GET['tags']) ? get('tags') : $cg['tags'];
if ($ij && is_array($ij) && count($ij) == 1 && !$ij[0]) {
$ij = '';
}
$ik = '';
$il = 'not in';
if ($ij) {
if (is_string($ij)) {
$ij = [$ij];
}
$im = implode("','", $ij);
$ik = "and `name` in ('{$im}')";
$il = 'in';
$in = " from {$bu} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$ht}\n                               where {$hz} and t._st=1  and t.tagid {$il}\n                               (select id from tags where type='{$bu}' {$ik} )\n                               {$hv}";
} else {
$in = " from {$bu} t1\n                              {$ht}\n                              where {$hz} and t1.id not in\n                              (select oid from tag_items where type='{$bu}')\n                              {$hv}";
}
} else {
$io = $hz;
if (RestHelper::isAdmin()) {
if ($bu == RestHelper::user_tbl()) {
$io = "t1.id={$be}";
}
}
$in = "from {$bu} t1 {$ht} where {$io} {$hu} {$hv}";
}
return $in;
}
public static function getSql($bu, $cg = array())
{
$be = RestHelper::uid();
$ip = RestHelper::get('sort', '_intm');
$iq = RestHelper::get('asc', -1);
if (!db::valid_table_col($bu, $ip)) {
$ip = '_intm';
}
$iq = $iq > 0 ? 'asc' : 'desc';
$hv = " order by t1.{$ip} {$iq}";
$ir = RestHelper::gets();
$ir = un_select_keys(['sort', 'asc'], $ir);
$is = RestHelper::get('_st', 1);
$dg = dissoc($ir, ['token', '_st']);
if ($is != 'all') {
$dg['_st'] = $is;
}
$hu = self::whereStr($dg, $bu);
$it = RestHelper::get('search', '');
$iu = RestHelper::get('search-key', '');
if ($it && $iu) {
$hu .= " and {$iu} like '%{$it}%'";
}
$iv = RestHelper::select_add();
$ht = RestHelper::join_add();
$iw = RestHelper::get('fields', []);
$ix = 't1.*';
if ($iw) {
$ix = '';
foreach ($iw as $iy) {
if ($ix) {
$ix .= ',';
}
$ix .= 't1.' . $iy;
}
}
$in = self::getSqlFrom($bu, $ht, $be, $hu, $hv, $cg);
$ct = "select {$ix} {$iv} {$in}";
$iz = "select count(*) cnt {$in}";
$ag = RestHelper::offset();
$af = RestHelper::pagesize();
$ct .= " limit {$ag},{$af}";
return [$ct, $iz];
}
public static function getResName($bu, $cg)
{
$jk = getArg($cg, 'res_name', '');
if ($jk) {
return $jk;
}
$jl = RestHelper::get('res_id_key', '');
if ($jl) {
$jm = RestHelper::get($jl);
$bu .= '_' . $jm;
}
return $bu;
}
public static function getList($bu, $cg = array())
{
$be = RestHelper::uid();
list($ct, $iz) = self::getSql($bu, $cg);
info($ct);
$cz = db::query($ct);
$ap = (int) db::queryOne($iz);
$jn = RestHelper::get_rest_join_tags_list();
if ($jn && in_array($bu, $jn)) {
$jo = getKeyValues($cz, 'id');
$ij = RestHelper::get_tags_by_oid($be, $jo, $bu);
info("get tags ok: {$be} {$bu} " . json_encode($jo));
foreach ($cz as $bx => $dp) {
if (isset($ij[$dp['id']])) {
$jp = $ij[$dp['id']];
$cz[$bx]['tags'] = getKeyValues($jp, 'name');
}
}
info('set tags ok');
}
if (isset($cg['join_cols'])) {
foreach ($cg['join_cols'] as $jq => $jr) {
$js = getArg($jr, 'jtype', '1-1');
$jt = getArg($jr, 'jkeys', []);
$ju = getArg($jr, 'jwhe', []);
$jv = getArg($jr, 'ast', ['id' => 'ASC']);
if (is_string($jr['on'])) {
$jw = 'id';
$jx = $jr['on'];
} else {
if (is_array($jr['on'])) {
$jy = array_keys($jr['on']);
$jw = $jy[0];
$jx = $jr['on'][$jw];
}
}
$jo = getKeyValues($cz, $jw);
$ju[$jx] = $jo;
$jz = \db::all($jq, ['AND' => $ju, 'ORDER' => $jv]);
foreach ($jz as $k => $kl) {
foreach ($cz as $bx => &$dp) {
if (isset($dp[$jw]) && isset($kl[$jx]) && $dp[$jw] == $kl[$jx]) {
if ($js == '1-1') {
foreach ($jt as $km => $kn) {
$dp[$kn] = $kl[$km];
}
}
$km = isset($jr['jkey']) ? $jr['jkey'] : $jq;
if ($js == '1-n') {
$dp[$km][] = $kl[$km];
}
if ($js == '1-n-o') {
$dp[$km][] = $kl;
}
if ($js == '1-1-o') {
$dp[$km] = $kl;
}
}
}
}
}
}
$jk = self::getResName($bu, $cg);
\ctx::count($ap);
$ko = ['pageinfo' => \ctx::pageinfo()];
return ['data' => $cz, 'res-name' => $jk, 'count' => $ap, 'meta' => $ko];
}
public static function renderList($bu)
{
ret(self::getList($bu));
}
public static function getItem($bu, $bv)
{
$be = RestHelper::uid();
info("---GET---: {$bu}/{$bv}");
$jk = "{$bu}-{$bv}";
if ($bu == 'colls') {
$du = db::row($bu, ["{$bu}.id" => $bv], ["{$bu}.id", "{$bu}.title", "{$bu}.from_url", "{$bu}._intm", "{$bu}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($bu == 'feeds') {
$c = RestHelper::get('type');
$kp = RestHelper::get('rid');
$du = db::row($bu, ['AND' => ['uid' => $be, 'rid' => $bv, 'type' => $c]]);
if (!$du) {
$du = ['rid' => $bv, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$jk = "{$jk}-{$c}-{$bv}";
} else {
$du = db::row($bu, ['id' => $bv]);
}
}
if ($kq = RestHelper::rest_extra_data()) {
$du = array_merge($du, $kq);
}
return ['data' => $du, 'res-name' => $jk, 'count' => 1];
}
public static function renderItem($bu, $bv)
{
ret(self::getItem($bu, $bv));
}
public static function postData($bu)
{
$ab = db::tbl_data($bu, RestHelper::data());
$be = RestHelper::uid();
$ij = [];
if ($bu == 'tags') {
$ij = RestHelper::get_tag_by_name($be, $ab['name'], $ab['type']);
}
if ($ij && $bu == 'tags') {
$ab = $ij[0];
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
$iy = $ab['inc'];
unset($ab['inc']);
db::exec("UPDATE {$bu} SET {$iy} = {$iy} + 1 WHERE id={$bv}");
}
if (isset($ab['dec'])) {
$iy = $ab['dec'];
unset($ab['dec']);
db::exec("UPDATE {$bu} SET {$iy} = {$iy} - 1 WHERE id={$bv}");
}
if (isset($ab['tags'])) {
RestHelper::del_tag_by_name($be, $bv, $bu);
$ij = $ab['tags'];
foreach ($ij as $kr) {
$ks = RestHelper::get_tag_by_name($be, $kr, $bu);
if ($ks) {
$kt = $ks[0]['id'];
RestHelper::save_tag_items($be, $kt, $bv, $bu);
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
$dg = ['AND' => ['id' => $bv], 'LIMIT' => 1];
$cz = db::obj()->select($bu, '*', $dg);
if ($cz) {
$du = $cz[0];
} else {
$du = null;
}
if ($du) {
if (array_key_exists('uid', $du)) {
$ku = $du['uid'];
if ($bu == RestHelper::user_tbl()) {
$ku = $du['id'];
}
if ($ku != $be && (!RestHelper::isAdmin() || !RestHelper::isAdminRest())) {
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
public static function ins($kv = null)
{
if ($kv) {
self::$_ins = $kv;
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
public static function get_tags_by_oid($be, $jo, $bu)
{
return self::ins()->get_tags_by_oid($be, $jo, $bu);
}
public static function get_tag_by_name($be, $bu, $c)
{
return self::ins()->get_tag_by_name($be, $bu, $c);
}
public static function del_tag_by_name($be, $bv, $bu)
{
return self::ins()->del_tag_by_name($be, $bv, $bu);
}
public static function save_tag_items($be, $kt, $bv, $bu)
{
return self::ins()->save_tag_items($be, $kt, $bv, $bu);
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
public static function get($bx, $kw = '')
{
return self::ins()->get($bx, $kw);
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
public function get_tags_by_oid($be, $jo, $bu);
public function get_tag_by_name($be, $bu, $c);
public function del_tag_by_name($be, $bv, $bu);
public function save_tag_items($be, $kt, $bv, $bu);
public function isAdmin();
public function isAdminRest();
public function user_tbl();
public function data();
public function uid();
public function get($bx, $kw);
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
public function get_tags_by_oid($be, $jo, $bu)
{
return tag::getTagsByOids($be, $jo, $bu);
}
public function get_tag_by_name($be, $bu, $c)
{
return tag::getTagByName($be, $bu, $c);
}
public function del_tag_by_name($be, $bv, $bu)
{
return tag::delTagByOid($be, $bv, $bu);
}
public function save_tag_items($be, $kt, $bv, $bu)
{
return tag::saveTagItems($be, $kt, $bv, $bu);
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
public function get($bx, $kw)
{
return get($bx, $kw);
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
public static function getTagByName($be, $kr, $c)
{
$ij = \db::all(self::$tbl_name, ['AND' => ['uid' => $be, 'name' => $kr, 'type' => $c, '_st' => 1]]);
return $ij;
}
public static function delTagByOid($be, $kx, $ky)
{
info("del tag: {$be}, {$kx}, {$ky}");
$v = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $be, 'oid' => $kx, 'type' => $ky]]);
info($v);
}
public static function saveTagItems($be, $kz, $kx, $ky)
{
\db::save('tag_items', ['tagid' => $kz, 'uid' => $be, 'oid' => $kx, 'type' => $ky, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($be, $c)
{
$ij = \db::all(self::$tbl_name, ['AND' => ['uid' => $be, 'type' => $c, '_st' => 1]]);
return $ij;
}
public static function getTagsByOid($be, $kx, $c)
{
$ct = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$kx} and t2.type='{$c}' and t2._st=1";
$cz = \db::query($ct);
return getKeyValues($cz, 'name');
}
public static function getTagsByOids($be, $lm, $c)
{
if (is_array($lm)) {
$lm = implode(',', $lm);
}
$ct = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$lm}) and t2.type='{$c}' and t2._st=1";
$cz = \db::query($ct);
$ab = groupArray($cz, 'oid');
return $ab;
}
public static function countByTag($be, $kr, $c)
{
$ct = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$kr}' and t1.type='{$c}' and t1.uid={$be}";
$cz = \db::query($ct);
return [$cz[0]['cnt'], $cz[0]['id']];
}
public static function saveTag($be, $kr, $c)
{
$ab = ['uid' => $be, 'name' => $kr, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$ab = \db::save('tags', $ab);
return $ab;
}
public static function countTags($be, $ln, $bu)
{
foreach ($ln as $kr) {
list($lo, $bv) = self::countByTag($be, $kr, $bu);
echo "{$kr} {$lo} {$bv} <br>";
\db::update('tags', ['count' => $lo], ['id' => $bv]);
}
}
public static function saveRepoTags($be, $lp)
{
$bu = 'stars';
echo count($lp) . "<br>";
$ln = [];
foreach ($lp as $lq) {
$lr = $lq['repoId'];
$ij = isset($lq['tags']) ? $lq['tags'] : [];
if ($ij) {
foreach ($ij as $kr) {
if (!in_array($kr, $ln)) {
$ln[] = $kr;
}
$ij = self::getTagByName($be, $kr, $bu);
if (!$ij) {
$ks = self::saveTag($be, $kr, $bu);
} else {
$ks = $ij[0];
}
$kz = $ks['id'];
$ls = getStarByRepoId($be, $lr);
if ($ls) {
$kx = $ls[0]['id'];
$lt = self::getTagsByOid($be, $kx, $bu);
if ($ks && !in_array($kr, $lt)) {
self::saveTagItems($be, $kz, $kx, $bu);
}
} else {
echo "-------- star for {$lr} not found <br>";
}
}
} else {
}
}
self::countTags($be, $ln, $bu);
}
public static function getTagItem($lu, $be, $lv, $ds, $lw)
{
$ct = "select * from {$lv} where {$ds}={$lw} and uid={$be}";
return $lu->query($ct)->fetchAll();
}
public static function saveItemTags($lu, $be, $bu, $lx, $ds = 'id')
{
echo count($lx) . "<br>";
$ln = [];
foreach ($lx as $ly) {
$lw = $ly[$ds];
$ij = isset($ly['tags']) ? $ly['tags'] : [];
if ($ij) {
foreach ($ij as $kr) {
if (!in_array($kr, $ln)) {
$ln[] = $kr;
}
$ij = getTagByName($lu, $be, $kr, $bu);
if (!$ij) {
$ks = saveTag($lu, $be, $kr, $bu);
} else {
$ks = $ij[0];
}
$kz = $ks['id'];
$ls = getTagItem($lu, $be, $bu, $ds, $lw);
if ($ls) {
$kx = $ls[0]['id'];
$lt = getTagsByOid($lu, $be, $kx, $bu);
if ($ks && !in_array($kr, $lt)) {
saveTagItems($lu, $be, $kz, $kx, $bu);
}
} else {
echo "-------- star for {$lw} not found <br>";
}
}
} else {
}
}
countTags($lu, $be, $ln, $bu);
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
$lz = null;
$ay = null;
$ab = \ctx::data();
$bh = $ab['auth_type'];
debug("auth type: {$bh}");
if ($bh) {
if ($bh == 'cube') {
info("cube auth ...");
$aw .= '$$cube';
$ak = cube_user_login($app, $ce, $cf);
if ($ak) {
$ak['luser'] = local_user('cube_uid', $ak['user']['id'], $bd);
cache_user($aw, $ak);
$ay = $ak['user'];
}
}
} else {
if ($bg) {
list($bi, $ay, $mn) = uc_user_login($app, $ce, $cf);
$ak = $ay;
$lz = ['access_token' => $bi, 'userinfo' => $ay, 'role_list' => $mn, 'luser' => local_user('uc_id', $ay['user_id'], $bd)];
extract(cache_user($aw, $lz));
$ay = select_keys(['username', 'phone', 'roles', 'email'], $ay);
} else {
$ak = user_login($app, $ce, $cf, $bd, 1);
if ($ak) {
$ak['username'] = $ak[$ce];
$lz = ['user' => $ak];
extract(cache_user($aw, $lz));
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
public function __construct($mo = array())
{
$this->items = $this->getArrayItems($mo);
}
public function add($dr, $l = null)
{
if (is_array($dr)) {
foreach ($dr as $k => $l) {
$this->add($k, $l);
}
} elseif (is_null($this->get($dr))) {
$this->set($dr, $l);
}
}
public function all()
{
return $this->items;
}
public function clear($dr = null)
{
if (is_null($dr)) {
$this->items = [];
return;
}
$dr = (array) $dr;
foreach ($dr as $k) {
$this->set($k, []);
}
}
public function delete($dr)
{
$dr = (array) $dr;
foreach ($dr as $k) {
if ($this->exists($this->items, $k)) {
unset($this->items[$k]);
continue;
}
$mo =& $this->items;
$mp = explode('.', $k);
$mq = array_pop($mp);
foreach ($mp as $mr) {
if (!isset($mo[$mr]) || !is_array($mo[$mr])) {
continue 2;
}
$mo =& $mo[$mr];
}
unset($mo[$mq]);
}
}
protected function exists($ms, $k)
{
return array_key_exists($k, $ms);
}
public function get($k = null, $mt = null)
{
if (is_null($k)) {
return $this->items;
}
if ($this->exists($this->items, $k)) {
return $this->items[$k];
}
if (strpos($k, '.') === false) {
return $mt;
}
$mo = $this->items;
foreach (explode('.', $k) as $mr) {
if (!is_array($mo) || !$this->exists($mo, $mr)) {
return $mt;
}
$mo =& $mo[$mr];
}
return $mo;
}
protected function getArrayItems($mo)
{
if (is_array($mo)) {
return $mo;
} elseif ($mo instanceof self) {
return $mo->all();
}
return (array) $mo;
}
public function has($dr)
{
$dr = (array) $dr;
if (!$this->items || $dr === []) {
return false;
}
foreach ($dr as $k) {
$mo = $this->items;
if ($this->exists($mo, $k)) {
continue;
}
foreach (explode('.', $k) as $mr) {
if (!is_array($mo) || !$this->exists($mo, $mr)) {
return false;
}
$mo = $mo[$mr];
}
}
return true;
}
public function isEmpty($dr = null)
{
if (is_null($dr)) {
return empty($this->items);
}
$dr = (array) $dr;
foreach ($dr as $k) {
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
$mo = (array) $this->get($k);
$l = array_merge($mo, $this->getArrayItems($l));
$this->set($k, $l);
} elseif ($k instanceof self) {
$this->items = array_merge($this->items, $k->all());
}
}
public function pull($k = null, $mt = null)
{
if (is_null($k)) {
$l = $this->all();
$this->clear();
return $l;
}
$l = $this->get($k, $mt);
$this->delete($k);
return $l;
}
public function push($k, $l = null)
{
if (is_null($l)) {
$this->items[] = $k;
return;
}
$mo = $this->get($k);
if (is_array($mo) || is_null($mo)) {
$mo[] = $l;
$this->set($k, $mo);
}
}
public function set($dr, $l = null)
{
if (is_array($dr)) {
foreach ($dr as $k => $l) {
$this->set($k, $l);
}
return;
}
$mo =& $this->items;
foreach (explode('.', $dr) as $k) {
if (!isset($mo[$k]) || !is_array($mo[$k])) {
$mo[$k] = [];
}
$mo =& $mo[$k];
}
$mo = $l;
}
public function setArray($mo)
{
$this->items = $this->getArrayItems($mo);
}
public function setReference(array &$mo)
{
$this->items =& $mo;
}
public function toJson($k = null, $ef = 0)
{
if (is_string($k)) {
return json_encode($this->get($k), $ef);
}
$ef = $k === null ? 0 : $k;
return json_encode($this->items, $ef);
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
public function __construct($mu = '')
{
if ($mu) {
$this->service = $mu;
$cg = self::$_services[$this->service];
$mv = $cg['url'];
debug("init client: {$mv}");
$this->client = new Client(['base_uri' => $mv, 'timeout' => 12.0]);
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
$mw = \cfg::get('service_list', 'service');
if ($mw) {
foreach ($mw as $m) {
self::add($m);
}
}
}
public function getRest($mu, $x = '/rest')
{
return $this->getService($mu, $x . '/');
}
public function getService($mu, $x = '')
{
if (isset(self::$_services[$mu])) {
if (!isset(self::$_ins[$mu])) {
self::$_ins[$mu] = new Service($mu);
}
}
if (isset(self::$_ins[$mu])) {
$kv = self::$_ins[$mu];
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
public function __call($hk, $mx)
{
$cg = self::$_services[$this->service];
$mv = $cg['url'];
$bk = $cg['appid'];
$bf = $cg['appkey'];
$my = getArg($mx, 0, []);
$ab = getArg($my, 'data', []);
$ab = array_merge($ab, $_GET);
unset($mz['token']);
$ab['appid'] = $bk;
$ab['date'] = date("Y-m-d H:i:s");
$ab['sign'] = gen_sign($ab, $bf);
$no = getArg($my, 'path', '');
$np = getArg($my, 'suffix', '');
$no = $this->prefix . $no . $np;
$hk = strtoupper($hk);
debug("api_url: {$bk} {$bf} {$mv}");
debug("api_name: {$no} [{$hk}]");
debug("data: " . json_encode($ab));
try {
if (in_array($hk, ['GET'])) {
$nq = $nr == 'GET' ? 'query' : 'form_params';
$this->resp = $this->client->request($hk, $no, [$nq => $ab]);
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
public function __get($ns)
{
$hk = 'get' . ucfirst($ns);
if (method_exists($this, $hk)) {
$nt = new ReflectionMethod($this, $hk);
if (!$nt->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $ns)) {
return $this->{$ns};
}
}
public function __set($ns, $l)
{
$hk = 'set' . ucfirst($ns);
if (method_exists($this, $hk)) {
$nt = new ReflectionMethod($this, $hk);
if (!$nt->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $ns)) {
$this->{$ns} = $l;
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
$nu = 100;
while (count($this->stack) && $nu > 0) {
$nu -= 1;
debug("count stack: " . count($this->stack));
$this->branchify(array_shift($this->stack));
}
}
protected function branchify(&$nv)
{
if ($this->pick_node_id) {
if ($nv['id'] == $this->pick_node_id) {
$this->addLeaf($this->tree, $nv);
return;
}
} else {
if (null === $nv[$this->pid_key] || 0 == $nv[$this->pid_key]) {
$this->addLeaf($this->tree, $nv);
return;
}
}
if (isset($this->leafIndex[$nv[$this->pid_key]])) {
$this->addLeaf($this->leafIndex[$nv[$this->pid_key]][$this->children_key], $nv);
} else {
debug("back to stack: " . json_encode($nv) . json_encode($this->leafIndex));
$this->stack[] = $nv;
}
}
protected function addLeaf(&$nw, $nv)
{
$nx = array('id' => $nv['id'], $this->name_key => $nv['name'], 'data' => $nv, $this->children_key => array());
foreach ($this->ext_keys as $bx => $by) {
if (isset($nv[$bx])) {
$nx[$by] = $nv[$bx];
}
}
$nw[] = $nx;
$this->leafIndex[$nv['id']] =& $nw[count($nw) - 1];
}
protected function addChild($nw, $nv)
{
$this->leafIndex[$nv['id']] &= $nw[$this->children_key][] = $nv;
}
public function getTree()
{
return $this->tree;
}
}
}
namespace {
if (getenv('WHOOPS_ENABLED') == 'yes') {
$ny = new \Whoops\Run();
$ny->pushHandler(new \Whoops\Handler\PrettyPageHandler());
$ny->register();
}
function getCaller($nz = NULL)
{
$op = debug_backtrace();
$oq = $op[2];
if (isset($nz)) {
return $oq[$nz];
} else {
return $oq;
}
}
function getCallerStr($or = 4)
{
$op = debug_backtrace();
$oq = $op[2];
$os = $op[1];
$ot = $oq['function'];
$ou = isset($oq['class']) ? $oq['class'] : '';
$ov = $os['file'];
$ow = $os['line'];
if ($or == 4) {
$bw = "{$ou} {$ot} {$ov} {$ow}";
} elseif ($or == 3) {
$bw = "{$ou} {$ot} {$ow}";
} else {
$bw = "{$ou} {$ow}";
}
return $bw;
}
function wlog($bs, $ox, $oy)
{
if (is_dir($bs)) {
$oz = date('Y-m-d', time());
$oy .= "\n";
file_put_contents($bs . "/{$ox}-{$oz}.log", $oy, FILE_APPEND);
}
}
function folder_exist($pq)
{
$bs = realpath($pq);
return ($bs !== false and is_dir($bs)) ? $bs : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($ab, $pr)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$ps = $m['symmetric_key'];
$pt = $m['hmac_key'];
$pu = new AES_SHA($ps, $pt);
return $pu->encrypt(serialize($ab), $pr);
}
function decrypt($ab)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$ps = $m['symmetric_key'];
$pt = $m['hmac_key'];
$pu = new AES_SHA($ps, $pt);
return unserialize($pu->decrypt($ab));
}
function encrypt_cookie($pv)
{
return encrypt($pv->getData(), $pv->getExpiration());
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($ek, $pw = 'DECODE', $k = '', $px = 0)
{
$py = 4;
$k = md5($k ? $k : UC_KEY);
$pz = md5(substr($k, 0, 16));
$qr = md5(substr($k, 16, 16));
$qs = $py ? $pw == 'DECODE' ? substr($ek, 0, $py) : substr(md5(microtime()), -$py) : '';
$qt = $pz . md5($pz . $qs);
$qu = strlen($qt);
$ek = $pw == 'DECODE' ? base64_decode(substr($ek, $py)) : sprintf('%010d', $px ? $px + time() : 0) . substr(md5($ek . $qr), 0, 16) . $ek;
$qv = strlen($ek);
$el = '';
$qw = range(0, 255);
$qx = array();
for ($er = 0; $er <= 255; $er++) {
$qx[$er] = ord($qt[$er % $qu]);
}
for ($qy = $er = 0; $er < 256; $er++) {
$qy = ($qy + $qw[$er] + $qx[$er]) % 256;
$en = $qw[$er];
$qw[$er] = $qw[$qy];
$qw[$qy] = $en;
}
for ($qz = $qy = $er = 0; $er < $qv; $er++) {
$qz = ($qz + 1) % 256;
$qy = ($qy + $qw[$qz]) % 256;
$en = $qw[$qz];
$qw[$qz] = $qw[$qy];
$qw[$qy] = $en;
$el .= chr(ord($ek[$er]) ^ $qw[($qw[$qz] + $qw[$qy]) % 256]);
}
if ($pw == 'DECODE') {
if ((substr($el, 0, 10) == 0 || substr($el, 0, 10) - time() > 0) && substr($el, 10, 16) == substr(md5(substr($el, 26) . $qr), 0, 16)) {
return substr($el, 26);
} else {
return '';
}
} else {
return $qs . str_replace('=', '', base64_encode($el));
}
}
function object2array(&$rs)
{
$rs = json_decode(json_encode($rs), true);
return $rs;
}
function getKeyValues($ab, $k, $cu = null)
{
if (!$cu) {
$cu = function ($by) {
return $by;
};
}
$bc = array();
if ($ab && is_array($ab)) {
foreach ($ab as $du) {
if (isset($du[$k]) && $du[$k]) {
$u = $du[$k];
if ($cu) {
$u = $cu($u);
}
$bc[] = $u;
}
}
}
return array_unique($bc);
}
if (!function_exists('indexArray')) {
function indexArray($ab, $k, $fj = null)
{
$bc = array();
if ($ab && is_array($ab)) {
foreach ($ab as $du) {
if (!isset($du[$k]) || !$du[$k] || !is_scalar($du[$k])) {
continue;
}
if (!$fj) {
$bc[$du[$k]] = $du;
} else {
if (is_string($fj)) {
$bc[$du[$k]] = $du[$fj];
} else {
if (is_array($fj)) {
$rt = [];
foreach ($fj as $bx => $by) {
$rt[$by] = $du[$by];
}
$bc[$du[$k]] = $du[$fj];
}
}
}
}
}
return $bc;
}
}
if (!function_exists('groupArray')) {
function groupArray($ms, $k)
{
if (!is_array($ms) || !$ms) {
return array();
}
$ab = array();
foreach ($ms as $du) {
if (isset($du[$k]) && $du[$k]) {
$ab[$du[$k]][] = $du;
}
}
return $ab;
}
}
function select_keys($dr, $ab)
{
$v = [];
foreach ($dr as $k) {
if (isset($ab[$k])) {
$v[$k] = $ab[$k];
} else {
$v[$k] = '';
}
}
return $v;
}
function un_select_keys($dr, $ab)
{
$v = [];
foreach ($ab as $bx => $du) {
if (!in_array($bx, $dr)) {
$v[$bx] = $du;
}
}
return $v;
}
function copyKey($ab, $ru, $rv)
{
foreach ($ab as &$du) {
$du[$rv] = $du[$ru];
}
return $ab;
}
function addKey($ab, $k, $u)
{
foreach ($ab as &$du) {
$du[$k] = $u;
}
return $ab;
}
function dissoc($ms, $dr)
{
if (is_array($dr)) {
foreach ($dr as $k) {
unset($ms[$k]);
}
} else {
unset($ms[$dr]);
}
return $ms;
}
function sortIdx($ab)
{
$rw = [];
foreach ($ab as $bx => $by) {
$rw[$by] = ['_sort' => $bx + 1];
}
return $rw;
}
function insertAt($mo, $rx, $l)
{
array_splice($mo, $rx, 0, [$l]);
return $mo;
}
function getArg($my, $ry, $mt = '')
{
if (isset($my[$ry])) {
return $my[$ry];
} else {
return $mt;
}
}
function permu($au, $dm = ',')
{
$ai = [];
if (is_string($au)) {
$rz = str_split($au);
} else {
$rz = $au;
}
sort($rz);
$st = count($rz) - 1;
$su = $st;
$ap = 1;
$du = implode($dm, $rz);
$ai[] = $du;
while (true) {
$sv = $su--;
if ($rz[$su] < $rz[$sv]) {
$sw = $st;
while ($rz[$su] > $rz[$sw]) {
$sw--;
}
list($rz[$su], $rz[$sw]) = array($rz[$sw], $rz[$su]);
for ($er = $st; $er > $sv; $er--, $sv++) {
list($rz[$er], $rz[$sv]) = array($rz[$sv], $rz[$er]);
}
$du = implode($dm, $rz);
$ai[] = $du;
$su = $st;
$ap++;
}
if ($su == 0) {
break;
}
}
return $ai;
}
function combin($bc, $sx, $sy = ',')
{
$el = array();
if ($sx == 1) {
return $bc;
}
if ($sx == count($bc)) {
$el[] = implode($sy, $bc);
return $el;
}
$sz = $bc[0];
unset($bc[0]);
$bc = array_values($bc);
$tu = combin($bc, $sx - 1, $sy);
foreach ($tu as $tv) {
$tv = $sz . $sy . $tv;
$el[] = $tv;
}
unset($tu);
$tw = combin($bc, $sx, $sy);
foreach ($tw as $tv) {
$el[] = $tv;
}
unset($tw);
return $el;
}
function getExcelCol($cv)
{
$bc = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($cv == 0) {
return '';
}
return getExcelCol((int) (($cv - 1) / 26)) . $bc[$cv % 26];
}
function getExcelPos($dp, $cv)
{
return getExcelCol($cv) . $dp;
}
function sendJSON($ab)
{
$tx = cfg::get('aca');
if (isset($tx['origin'])) {
header("Access-Control-Allow-Origin: {$tx['origin']}");
}
$ty = "Content-Type, Authorization, Accept,X-Requested-With";
if (isset($tx['headers'])) {
$ty = $tx['headers'];
}
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: {$ty}");
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
function succ($bc = array(), $tz = 'succ', $uv = 1)
{
$ab = $bc;
$uw = 0;
$ux = 1;
$ap = 0;
$v = array($tz => $uv, 'errormsg' => '', 'errorfield' => '');
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
function fail($bc = array(), $tz = 'succ', $uy = 0)
{
$k = $oy = '';
if (count($bc) > 0) {
$dr = array_keys($bc);
$k = $dr[0];
$oy = $bc[$k][0];
}
$v = array($tz => $uy, 'errormsg' => $oy, 'errorfield' => $k);
sendJSON($v);
}
function code($bc = array(), $fm = 0)
{
if (is_string($fm)) {
}
if ($fm == 0) {
succ($bc, 'code', 0);
} else {
fail($bc, 'code', $fm);
}
}
function ret($bc = array(), $fm = 0, $iy = '')
{
$qz = $bc;
$uz = $fm;
if (is_numeric($bc) || is_string($bc)) {
$uz = $bc;
$qz = array();
if (is_array($fm)) {
$qz = $fm;
} else {
$fm = $fm === 0 ? '' : $fm;
$qz = array($iy => array($fm));
}
}
code($qz, $uz);
}
function response($bc = array(), $fm = 0, $iy = '')
{
ret($bc, $fm, $iy);
}
function err($vw)
{
code($vw, 1);
}
function downloadExcel($vx, $eo)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $eo . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$vx->save('php://output');
}
function dd($ab)
{
if ($_SERVER['HTTP_ACCEPT'] == 'application/json') {
ret(['dd' => $ab]);
} else {
dump($ab);
exit;
}
}
function cacert_file()
{
return ROOT_PATH . "/fn/cacert.pem";
}
function curl($fh, $vy = 10, $vz = 30, $wx = '', $hk = 'post')
{
$wy = curl_init($fh);
curl_setopt($wy, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($wy, CURLOPT_CONNECTTIMEOUT, $vy);
curl_setopt($wy, CURLOPT_HEADER, 0);
curl_setopt($wy, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($wy, CURLOPT_TIMEOUT, $vz);
if (file_exists(cacert_file())) {
curl_setopt($wy, CURLOPT_CAINFO, cacert_file());
}
if ($wx) {
if (is_array($wx)) {
$wx = http_build_query($wx);
}
if ($hk == 'post') {
curl_setopt($wy, CURLOPT_POST, 1);
} else {
if ($hk == 'put') {
curl_setopt($wy, CURLOPT_CUSTOMREQUEST, "put");
}
}
curl_setopt($wy, CURLOPT_POSTFIELDS, $wx);
}
$el = curl_exec($wy);
if (curl_errno($wy)) {
return '';
}
curl_close($wy);
return $el;
}
function curl_header($fh, $vy = 10, $vz = 30)
{
$wy = curl_init($fh);
curl_setopt($wy, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($wy, CURLOPT_CONNECTTIMEOUT, $vy);
curl_setopt($wy, CURLOPT_HEADER, 1);
curl_setopt($wy, CURLOPT_NOBODY, 1);
curl_setopt($wy, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($wy, CURLOPT_TIMEOUT, $vz);
if (file_exists(cacert_file())) {
curl_setopt($wy, CURLOPT_CAINFO, cacert_file());
}
$el = curl_exec($wy);
if (curl_errno($wy)) {
return '';
}
return $el;
}
function http($fh, $cg = array())
{
$vy = getArg($cg, 'connecttime', 10);
$vz = getArg($cg, 'timeout', 30);
$ab = getArg($cg, 'data', '');
$hk = getArg($cg, 'method', 'get');
$ty = getArg($cg, 'headers', null);
$wy = curl_init($fh);
curl_setopt($wy, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($wy, CURLOPT_CONNECTTIMEOUT, $vy);
curl_setopt($wy, CURLOPT_HEADER, 0);
curl_setopt($wy, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($wy, CURLOPT_TIMEOUT, $vz);
if (file_exists(cacert_file())) {
curl_setopt($wy, CURLOPT_CAINFO, cacert_file());
}
if ($ty) {
curl_setopt($wy, CURLOPT_HTTPHEADER, $ty);
}
if ($ab) {
curl_setopt($wy, CURLOPT_POST, 1);
if (is_array($ab)) {
$ab = http_build_query($ab);
}
curl_setopt($wy, CURLOPT_POSTFIELDS, $ab);
}
if ($hk != 'get') {
if ($hk == 'post') {
curl_setopt($wy, CURLOPT_POST, 1);
} else {
if ($hk == 'put') {
curl_setopt($wy, CURLOPT_CUSTOMREQUEST, "put");
}
}
}
$el = curl_exec($wy);
if (curl_errno($wy)) {
return '';
}
curl_close($wy);
return $el;
}
function startWith($bw, $tv)
{
return strpos($bw, $tv) === 0;
}
function endWith($wz, $xy)
{
$xz = strlen($xy);
if ($xz == 0) {
return true;
}
return substr($wz, -$xz) === $xy;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $ab, $yz = false, $iy = '')
{
$ms = getKeyValues($ab, $k);
if (!$ms) {
return '';
}
if ($yz) {
foreach ($ms as $bx => $by) {
$ms[$bx] = "'{$by}'";
}
}
$bw = implode(',', $ms);
if ($iy) {
$k = $iy;
}
return " {$k} in ({$bw})";
}
function get_top_domain($fh)
{
$gl = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($gl, $fh, $abc);
if (count($abc) > 0) {
return $abc[0];
} else {
$abd = parse_url($fh);
$abe = $abd["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($abe))), $abe)) {
return $abe;
} else {
$bc = explode(".", $abe);
$ap = count($bc);
$abf = array("com", "net", "org", "3322");
if (in_array($bc[$ap - 2], $abf)) {
$gy = $bc[$ap - 3] . "." . $bc[$ap - 2] . "." . $bc[$ap - 1];
} else {
$gy = $bc[$ap - 2] . "." . $bc[$ap - 1];
}
return $gy;
}
}
}
function genID($os)
{
list($abg, $abh) = explode(" ", microtime());
$abi = rand(0, 100);
return $os . $abh . substr($abg, 2, 6);
}
function cguid($abj = false)
{
mt_srand((double) microtime() * 10000);
$abk = md5(uniqid(rand(), true));
return $abj ? strtoupper($abk) : $abk;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$abl = cguid();
$abm = chr(45);
$abn = chr(123) . substr($abl, 0, 8) . $abm . substr($abl, 8, 4) . $abm . substr($abl, 12, 4) . $abm . substr($abl, 16, 4) . $abm . substr($abl, 20, 12) . chr(125);
return $abn;
}
}
function randstr($lo = 6)
{
return substr(md5(rand()), 0, $lo);
}
function hashsalt($cf, $abo = '')
{
$abo = $abo ? $abo : randstr(10);
$abp = md5(md5($cf) . $abo);
return [$abp, $abo];
}
function gen_letters($lo = 26)
{
$tv = '';
for ($er = 65; $er < 65 + $lo; $er++) {
$tv .= strtolower(chr($er));
}
return $tv;
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
$abq = '';
foreach ($az as $k => $u) {
$abq .= $k . (is_array($u) ? assemble($u) : $u);
}
return $abq;
}
function check_sign($az, $aw = null)
{
$abq = getArg($az, 'sign');
$abr = getArg($az, 'date');
$abs = strtotime($abr);
$abt = time();
$abu = $abt - $abs;
debug("check_sign : {$abt} - {$abs} = {$abu}");
if (!$abr || $abt - $abs > 60) {
debug("check_sign fail : {$abr} delta > 60");
return false;
}
unset($az['sign']);
$abv = gen_sign($az, $aw);
debug("{$abq} -- {$abv}");
return $abq == $abv;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$abw = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$abw = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$abw = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$abw = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$abw = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$abw = getenv("REMOTE_ADDR");
} else {
$abw = "Unknown";
}
}
}
}
}
}
return $abw;
}
function getRIP()
{
$abw = $_SERVER["REMOTE_ADDR"];
return $abw;
}
function env($k = 'DEV_MODE', $mt = '')
{
$l = getenv($k);
return $l ? $l : $mt;
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
$abx = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $abx) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
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
function cache($k, $cu = null, $abh = 10, $aby = 0)
{
$abz = new FilesystemCache();
if ($cu) {
if (is_callable($cu)) {
if ($aby || !$abz->has($k)) {
$ab = $cu();
debug("--------- fn: no cache for [{$k}] ----------");
$abz->set($k, $ab, $abh);
} else {
$ab = $abz->get($k);
debug("======= fn: data from cache [{$k}] ========");
}
} else {
debug("--------- set cache for [{$k}] ---------- : " . json_encode($cu));
$abz->set($k, $cu, $abh);
$ab = $cu;
}
} else {
debug("--------- get cache for [{$k}] ---------- ");
$ab = $abz->get($k);
}
return $ab;
}
function cache_del($k)
{
$abz = new FilesystemCache();
$abz->delete($k);
debug("!!!!!!--------- delete cache for [{$k}] ----------!!!!!!");
}
function cache_clear()
{
$abz = new FilesystemCache();
$abz->clear();
debug("!!!!!!--------- clear all cache ----------!!!!!!");
}
function baseModel($acd)
{
return '<' . <<<EOF
?php
namespace Entities {
class {$acd}
{
    protected \$id;
    protected \$intm;
    protected \$st;

EOF;
}
function baseArray($acd, $dy)
{
return array("Entities\\{$acd}" => array('type' => 'entity', 'table' => $dy, 'id' => array('id' => array('type' => 'integer', 'id' => true, 'generator' => array('strategy' => 'IDENTITY'))), 'fields' => array('intm' => array('type' => 'datetime', 'column' => '_intm'), 'st' => array('type' => 'integer', 'column' => '_st', 'options' => array('default' => 1)))));
}
function genObj($acd)
{
$iw = array_merge(\db::col_array('sys_objects'), ['sys_object_item.name(itemname)', 'sys_object_item.colname', 'sys_object_item.type', 'sys_object_item.length', 'sys_object_item.default', 'sys_object_item.comment']);
$dm = ['[>]sys_object_item' => ['id' => 'oid']];
$dv = ['AND' => ['sys_objects.name' => $acd], 'ORDER' => ['sys_objects.id' => 'DESC']];
$cz = \db::all('sys_objects', $dv, $iw, $dm);
if ($cz) {
$dy = $cz[0]['table'];
$ab = baseArray($acd, $dy);
$ace = baseModel($acd);
foreach ($cz as $dp) {
if (!$dp['itemname']) {
continue;
}
$acf = $dp['colname'] ? $dp['colname'] : $dp['itemname'];
$iy = ['type' => "{$dp['type']}", 'column' => "{$acf}", 'options' => array('default' => "{$dp['default']}", 'comment' => "{$dp['comment']}")];
$ab['Entities\\' . $acd]['fields'][$dp['itemname']] = $iy;
$ace .= "    protected \${$dp['itemname']}; \n";
}
$ace .= '}}';
}
return [$ab, $ace];
}
function writeObjFile($acd)
{
list($ab, $ace) = genObj($acd);
$acg = \Symfony\Component\Yaml\Yaml::dump($ab);
$ach = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$aci = $ach . '/src/objs';
if (!is_dir($aci)) {
mkdir($aci);
}
file_put_contents("{$aci}/{$acd}.php", $ace);
file_put_contents("{$aci}/Entities.{$acd}.dcm.yml", $acg);
}
function sync_to_db($acj = 'run')
{
echo $acj;
$ach = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$acj = "cd {$ach} && sh ./{$acj}.sh";
exec($acj, $ms);
foreach ($ms as $du) {
echo \SqlFormatter::format($du);
}
}
function gen_schema($ack, $acl, $acm = false, $acn = false)
{
$aco = true;
$acp = ROOT_PATH . '/tools/bin/db';
$acq = [$acp . "/yml", $acp . "/src/objs"];
$e = \Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($acq, $aco);
$acr = \Doctrine\ORM\EntityManager::create($ack, $e);
$acs = $acr->getConnection()->getDatabasePlatform();
$acs->registerDoctrineTypeMapping('enum', 'string');
$act = [];
foreach ($acl as $acu) {
$acv = $acu['name'];
include_once "{$acp}/src/objs/{$acv}.php";
$act[] = $acr->getClassMetadata('Entities\\' . $acv);
}
$acw = new \Doctrine\ORM\Tools\SchemaTool($acr);
$acx = $acw->getUpdateSchemaSql($act, true);
if (!$acx) {
}
$acy = [];
$acz = [];
foreach ($acx as $du) {
if (startWith($du, 'DROP')) {
$acy[] = $du;
}
$acz[] = \SqlFormatter::format($du);
}
if ($acm && !$acy || $acn) {
$v = $acw->updateSchema($act, true);
}
return $acz;
}
function gen_dbc_schema($acl)
{
$ade = \db::dbc();
$ack = ['driver' => 'pdo_mysql', 'host' => $ade['server'], 'user' => $ade['username'], 'password' => $ade['password'], 'dbname' => $ade['database_name']];
$acm = get('write', false);
$adf = get('force', false);
$acx = gen_schema($ack, $acl, $acm, $adf);
return ['database' => $ade['database_name'], 'sqls' => $acx];
}
function gen_corp_schema($co, $acl)
{
\db::switch_dbc($co);
return gen_dbc_schema($acl);
}
function buildcmd($cg = array())
{
$adg = new ptlis\ShellCommand\CommandBuilder();
$my = ['LC_CTYPE=en_US.UTF-8'];
if (isset($cg['args'])) {
$my = $cg['args'];
}
if (isset($cg['add_args'])) {
$my = array_merge($my, $cg['add_args']);
}
$adh = $adg->setCommand('/usr/bin/env')->addArguments($my)->buildCommand();
return $adh;
}
function exec_git($cg = array())
{
$bs = '.';
if (isset($cg['path'])) {
$bs = $cg['path'];
}
$my = ["/usr/bin/git", "--git-dir={$bs}/.git", "--work-tree={$bs}"];
$acj = 'status';
if (isset($cg['cmd'])) {
$acj = $cg['cmd'];
}
$my[] = $acj;
$adh = buildcmd(['add_args' => $my, $acj]);
$el = $adh->runSynchronous();
return $el->getStdOutLines();
}
use db\Rest as rest;
function getMetaData($acd, $adi = array())
{
ctx::pagesize(50);
$acl = db::all('sys_objects');
$adj = array_filter($acl, function ($by) use($acd) {
return $by['name'] == $acd;
});
$adj = array_shift($adj);
$adk = $adj['id'];
$adl = db::all('sys_object_item', ['oid' => $adk]);
$adm = ['Id'];
$adn = [0];
$ado = [0.1];
$dl = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($adl as $du) {
$bu = $du['name'];
$acf = $du['colname'] ? $du['colname'] : $bu;
$c = $du['type'];
$mt = $du['default'];
$adp = $du['col_width'];
$adq = $du['readonly'] ? ture : false;
$adr = $du['is_meta'];
if ($adr) {
$adm[] = $bu;
$adn[$acf] = $bu;
$ado[] = (double) $adp;
if (in_array($acf, array_keys($adi))) {
$dl[] = $adi[$acf];
} else {
$dl[] = ['data' => $acf, 'renderer' => 'html', 'readOnly' => $adq];
}
}
}
$adm[] = "InTm";
$adm[] = "St";
$ado[] = 60;
$ado[] = 10;
$dl[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$dl[] = ['data' => "_st", 'renderer' => "html"];
$ko = ['objname' => $acd];
return [$ko, $adm, $adn, $ado, $dl];
}
function getHotData($acd, $adi = array())
{
$adm[] = "InTm";
$adm[] = "St";
$ado[] = 60;
$ado[] = 10;
$dl[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$dl[] = ['data' => "_st", 'renderer' => "html"];
$ko = ['objname' => $acd];
return [$ko, $adm, $ado, $dl];
}
function fixfn($cj)
{
foreach ($cj as $ck) {
if (!function_exists($ck)) {
eval("function {$ck}(){}");
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
function idxtree($ads, $adt)
{
$jo = [];
$ab = \db::all($ads, ['pid' => $adt]);
$adu = getKeyValues($ab, 'id');
if ($adu) {
foreach ($adu as $adt) {
$jo = array_merge($jo, idxtree($ads, $adt));
}
}
return array_merge($adu, $jo);
}
function treelist($ads, $adt)
{
$nx = \db::row($ads, ['id' => $adt]);
$adv = $nx['sub_ids'];
$adv = json_decode($adv, true);
$adw = \db::all($ads, ['id' => $adv]);
$adx = 0;
foreach ($adw as $bx => $ady) {
if ($ady['pid'] == $adt) {
$adw[$bx]['pid'] = 0;
$adx++;
}
}
if ($adx < 2) {
$adw[] = [];
}
return $adw;
return array_merge([$nx], $adw);
}
function switch_domain($aw, $co)
{
$ak = cache($aw);
$ak['userinfo']['corpid'] = $co;
cache_user($aw, $ak);
$adz = [];
$aef = ms('master');
if ($aef) {
$cp = ms('master')->get(['path' => '/master/corp/apps', 'data' => ['corpid' => $co]]);
$adz = $cp->json();
$adz = getArg($adz, 'data');
}
return $adz;
}
function auto_reg_user($aeg = 'username', $aeh = 'password', $cr = 'user', $aei = 0)
{
$ce = randstr(10);
$cf = randstr(6);
$ab = ["{$aeg}" => $ce, "{$aeh}" => $cf, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($aei) {
list($cf, $abo) = hashsalt($cf);
$ab[$aeh] = $cf;
$ab['salt'] = $abo;
} else {
$ab[$aeh] = md5($cf);
}
return db::save($cr, $ab);
}
function refresh_token($cr, $be, $gy = '')
{
$aej = cguid();
$ab = ['id' => $be, 'token' => $aej];
$ak = db::save($cr, $ab);
if ($gy) {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/', $gy);
} else {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/');
}
return $ak;
}
function local_user($aek, $kp, $cr = 'user')
{
return \db::row($cr, [$aek => $kp]);
}
function user_login($app, $aeg = 'username', $aeh = 'password', $cr = 'user', $aei = 0)
{
$ab = ctx::data();
$ab = select_keys([$aeg, $aeh], $ab);
$ce = $ab[$aeg];
$cf = $ab[$aeh];
if (!$ce || !$cf) {
return NULL;
}
$ak = \db::row($cr, ["{$aeg}" => $ce]);
if ($ak) {
if ($aei) {
$abo = $ak['salt'];
list($cf, $abo) = hashsalt($cf, $abo);
} else {
$cf = md5($cf);
}
if ($cf == $ak[$aeh]) {
return $ak;
}
}
return NULL;
}
function uc_auto_reg_user($ey, $ael)
{
$v = \uc::find_user(['username' => $ey]);
if ($v['code'] != 0) {
$v = uc::reg_user($ey, $ael);
} else {
$v = ['code' => 1, 'data' => []];
}
return $v;
}
function uc_login_data($bi)
{
$ay = uc::user_info($bi);
$ay = $ay['data'];
$ft = [];
$aem = uc::user_role($bi, 1);
$mn = [];
if ($aem['code'] == 0) {
$mn = $aem['data']['roles'];
if ($mn) {
foreach ($mn as $k => $fr) {
$ft[] = $fr['name'];
}
}
}
$ay['roles'] = $ft;
$aen = uc::user_domain($bi);
$ay['corps'] = array_values($aen['data']);
return [$bi, $ay, $mn];
}
function uc_user_login($app, $aeg = 'username', $aeh = 'password')
{
log_time("uc_user_login start");
$uz = $app->getContainer();
$z = $uz->request;
$ab = $z->getParams();
$ab = select_keys([$aeg, $aeh], $ab);
$ce = $ab[$aeg];
$cf = $ab[$aeh];
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
function cube_user_login($app, $aeg = 'username', $aeh = 'password')
{
$uz = $app->getContainer();
$z = $uz->request;
$ab = $z->getParams();
if (isset($ab['code']) && isset($ab['bind_type'])) {
$e = getWxConfig('ucode');
$aeo = \EasyWeChat\Factory::miniProgram($e);
$aep = $aeo->auth->session($ab['code']);
$ab = cube::openid_login($aep['openid'], $ab['bind_type']);
} else {
$aeq = select_keys([$aeg, $aeh], $ab);
$ce = $aeq[$aeg];
$cf = $aeq[$aeh];
if (!$ce || !$cf) {
return NULL;
}
$ab = cube::login($ce, $cf);
}
$ak = cube::user();
if (!$ak) {
return NULL;
}
$ak['user']['modules'] = cube::modules()['modules'];
$ak['passport'] = cube::$passport;
$ft = cube::roles()['roles'];
$aer = indexArray($ft, 'id');
$mn = [];
if ($ak['user']['roles']) {
foreach ($ak['user']['roles'] as &$aes) {
$aes['name'] = $aer[$aes['role_id']]['name'];
$aes['title'] = $aer[$aes['role_id']]['title'];
$aes['description'] = $aer[$aes['role_id']]['description'];
$mn[] = $aes['name'];
}
}
$ak['user']['role_list'] = $ak['user']['roles'];
$ak['user']['roles'] = $mn;
return $ak;
}
function check_auth($app)
{
$z = req();
$aet = false;
$aeu = cfg::get('public_paths');
$gh = $z->getUri()->getPath();
if ($gh == '/') {
$aet = true;
} else {
foreach ($aeu as $bs) {
if (startWith($gh, $bs)) {
$aet = true;
}
}
}
info("check_auth: {$aet} {$gh}");
if (!$aet) {
if (is_weixin()) {
$gz = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $gz);
}
ret(1, 'auth error');
}
}
function extractUserData($aev)
{
return ['githubLogin' => $aev['login'], 'githubName' => $aev['name'], 'githubId' => $aev['id'], 'repos_url' => $aev['repos_url'], 'avatar_url' => $aev['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ak, $aew = false)
{
unset($ak['passwd']);
unset($ak['salt']);
if (!$aew) {
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
function tpl($bq, $aex = '.html')
{
$bq = $bq . $aex;
$aey = cfg::get('tpl_prefix');
$aez = "{$aey['pc']}/{$bq}";
$afg = "{$aey['mobile']}/{$bq}";
info("tpl: {$aez} | {$afg}");
return isMobile() ? $afg : $aez;
}
function req()
{
return ctx::req();
}
function get($bu, $mt = '')
{
$z = req();
$u = $z->getParam($bu, $mt);
if ($u == $mt) {
$afh = ctx::gets();
if (isset($afh[$bu])) {
return $afh[$bu];
}
}
return $u;
}
function post($bu, $mt = '')
{
$z = req();
return $z->getParam($bu, $mt);
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
$gh = $z->getUri()->getPath();
if (!startWith($gh, '/')) {
$gh = '/' . $gh;
}
return $gh;
}
function host_str($tv)
{
$afi = '';
if (isset($_SERVER['HTTP_HOST'])) {
$afi = $_SERVER['HTTP_HOST'];
}
return " [ {$afi} ] " . $tv;
}
function debug($tv)
{
$m = \cfg::get('app');
if (isset($m['log_level']) && $m['log_level'] == 100) {
if (ctx::logger()) {
$tv = format_log_str($tv, getCallerStr(3));
ctx::logger()->debug(host_str($tv));
}
}
}
function warn($tv)
{
if (ctx::logger()) {
$tv = format_log_str($tv, getCallerStr(3));
ctx::logger()->warn(host_str($tv));
}
}
function info($tv)
{
if (ctx::logger()) {
$tv = format_log_str($tv, getCallerStr(3));
ctx::logger()->info(host_str($tv));
}
}
function format_log_str($tv, $afj = '')
{
if (is_array($tv)) {
$tv = json_encode($tv);
}
return "{$tv} [ ::{$afj} ]";
}
function ck_owner($du)
{
$be = ctx::uid();
$ku = $du['uid'];
debug("ck_owner: {$be} {$ku}");
return $be == $ku;
}
function _err($bu)
{
return cfg::get($bu, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bw = '', $abs = 0)
{
global $__log_time__, $__log_begin_time__;
list($abg, $abh) = explode(" ", microtime());
$afk = (double) $abg + (double) $abh;
if (!$__log_time__) {
$__log_begin_time__ = $afk;
$__log_time__ = $afk;
$bs = uripath();
debug("usetime: --- {$bs} ---");
return $afk;
}
if ($abs && $abs == 'begin') {
$afl = $__log_begin_time__;
} else {
$afl = $abs ? $abs : $__log_time__;
}
$abu = $afk - $afl;
$abu *= 1000;
debug("usetime: ---  {$abu} {$bw}  ---");
$__log_time__ = $afk;
return $afk;
}
use core\Service as ms;
$p = $app->getContainer();
$p['view'] = function ($uz) {
$br = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$br->addExtension(new \Slim\Views\TwigExtension($uz['router'], $uz['request']->getUri()));
return $br;
};
$p['logger'] = function ($uz) {
if (is_docker_env()) {
$afm = '/ws/log/app.log';
} else {
$afn = cfg::get('logdir');
if ($afn) {
$afm = $afn . '/app.log';
} else {
$afm = __DIR__ . '/../app.log';
}
}
$afo = ['name' => '', 'path' => $afm];
$afp = new \Monolog\Logger($afo['name']);
$afp->pushProcessor(new \Monolog\Processor\UidProcessor());
$afq = \cfg::get('app');
$or = isset($afq['log_level']) ? $afq['log_level'] : '';
if (!$or) {
$or = \Monolog\Logger::INFO;
}
$afp->pushHandler(new \Monolog\Handler\StreamHandler($afo['path'], $or));
return $afp;
};
log_time();
unset($app->getContainer()['phpErrorHandler']);
unset($app->getContainer()['errorHandler']);
$p['notFoundHandler'] = function ($uz) {
if (!\ctx::isFoundRoute()) {
return function ($fx, $fy) use($uz) {
return $uz['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($fx, $fy) use($uz) {
return $uz['response'];
};
};
$p['ms'] = function ($uz) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($iy, $l, array $az) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$afr = ROOT_PATH . '/routes';
if (folder_exist($afr)) {
$q = dir::scan($afr, ['type' => 'file']);
foreach ($q as $r) {
if (basename($r) != 'routes.php' && !endWith($r, '.DS_Store')) {
require_once $r;
}
}
}
$afs = cfg::get('opt_route_list');
if ($afs) {
foreach ($afs as $aj) {
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
$aft = cache('blockly_routes_key', function () {
return db::all('sys_blockly', ['AND' => ['code_type' => 'route']]);
}, 86400, 1);
foreach ($aft as $afu) {
$afv = get('nb');
if ($afv != 1) {
@eval($afu['phpcode']);
}
}
}
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $bu, $ef = array())
{
$app->options("/hot/{$bu}", function () {
ret([]);
});
$app->options("/hot/{$bu}/{id}", function () {
ret([]);
});
$app->get("/hot/{$bu}", function () use($ef, $bu) {
$acd = $ef['objname'];
$afw = $bu;
$cz = rest::getList($afw);
$adi = isset($ef['cols_map']) ? $ef['cols_map'] : [];
list($ko, $adm, $adn, $ado, $dl) = getMetaData($acd, $adi);
$ado[0] = 10;
$v['data'] = ['meta' => $ko, 'list' => $cz['data'], 'colHeaders' => $adm, 'colWidths' => $ado, 'cols' => $dl];
ret($v);
});
$app->get("/hot/{$bu}/param", function () use($ef, $bu) {
$acd = $ef['objname'];
$afw = $bu;
$cz = rest::getList($afw);
list($adm, $afx, $adn, $ado, $dl, $afy) = getHotColMap1($afw, ['param_pid' => $cz['data'][0]['id']]);
$ko = ['objname' => $acd];
$ado[0] = 10;
$v['data'] = ['meta' => $ko, 'list' => [], 'colHeaders' => $adm, 'colHeaderDatas' => $adn, 'colHeaderGroupDatas' => $afx, 'colWidths' => $ado, 'cols' => $dl, 'origin_data' => $afy];
ret($v);
});
$app->post("/hot/{$bu}", function () use($ef, $bu) {
$afw = $bu;
$cz = rest::postData($afw);
ret($cz);
});
$app->put("/hot/{$bu}/{id}", function ($z, $bo, $my) use($ef, $bu) {
$afw = $bu;
$ab = ctx::data();
if (isset($ab['trans-word']) && isset($ab[$ab['trans-from']])) {
$afz = $ab['trans-from'];
$agh = $ab['trans-to'];
$u = util\Pinyin::get($ab[$afz]);
$ab[$agh] = $u;
}
ctx::data($ab);
$cz = rest::putData($afw, $my['id']);
ret($cz);
});
}
function getHotColMap1($afw, $cg = array())
{
$agi = get('pname', '_param');
$agj = get('oname', '_opt');
$agk = get('ename', '_opt_ext');
$agl = get('lname', 'label');
$agm = getArg($cg, 'param_pid', 0);
$agn = $afw . $agi;
$ago = $afw . $agj;
$agp = $afw . $agk;
ctx::pagesize(50);
if ($agm) {
ctx::gets('pid', $agm);
}
$cz = rest::getList($agn, $cg);
$agq = getKeyValues($cz['data'], 'id');
$az = indexArray($cz['data'], 'id');
$cg = db::all($ago, ['AND' => ['pid' => $agq]]);
$cg = indexArray($cg, 'id');
$agq = array_keys($cg);
$agr = db::all($agp, ['AND' => ['pid' => $agq]]);
$agr = groupArray($agr, 'pid');
$ags = getParamOptExt($az, $cg, $agr);
$adm = [];
$adn = [];
$afx = [];
$ado = [];
$dl = [];
foreach ($az as $k => $agt) {
$adm[] = $agt[$agl];
$afx[$agt['name']] = $agt['group_name'] ? $agt['group_name'] : $agt[$agl];
$adn[$agt['name']] = $agt[$agl];
$ado[] = $agt['width'];
$dl[$agt['name']] = ['data' => $agt['name'], 'renderer' => 'html'];
}
foreach ($agr as $k => $et) {
$agu = '';
$adt = 0;
$agv = $cg[$k];
$agw = $agv['pid'];
$agt = $az[$agw];
$agx = $agt[$agl];
$agu = $agt['name'];
$agy = $agt['type'];
if ($adt) {
}
if ($agu) {
$cv = ['data' => $agu, 'type' => 'autocomplete', 'strict' => false, 'source' => array_values(getKeyValues($et, 'option'))];
if ($agy == 'select2') {
$cv['editor'] = 'select2';
$agz = [];
foreach ($et as $ahi) {
$du['id'] = $ahi['id'];
$du['text'] = $ahi['option'];
$agz[] = $du;
}
$cv['select2Options'] = ['data' => $agz, 'dropdownAutoWidth' => true, 'width' => 'resolve'];
unset($cv['type']);
}
$dl[$agu] = $cv;
}
}
$dl = array_values($dl);
return [$adm, $afx, $adn, $ado, $dl, $ags];
}
function getParamOptExt($az, $cg, $agr)
{
$ef = [];
$ahj = [];
foreach ($agr as $k => $ahk) {
$agv = $cg[$k];
$agj = $agv['name'];
$agw = $agv['pid'];
foreach ($ahk as $ahi) {
$ahl = $ahi['_rownum'];
$ef[$agw][$ahl][$agj] = $ahi['option'];
$ahj[$agw][$ahl][$agj] = $ahi;
}
}
foreach ($ahj as $bv => $ahi) {
$az[$bv]['opt_exts'] = array_values($ahi);
}
foreach ($ef as $bv => $du) {
$az[$bv]['options'] = array_values($du);
}
$ab = array_values($az);
return $ab;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $bu, $ef = array())
{
$afw = $bu;
$ahm = "{$bu}_ext";
$app->get("/hot/{$bu}", function () use($afw, $ahm) {
$kx = get('oid');
$adt = get('pid');
$ct = "select * from `{$afw}` pp join `{$ahm}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$kx} and pp.pid={$adt}";
$cz = db::query($ct);
$ab = groupArray($cz, 'name');
$adm = ['Id', 'Oid', 'RowNum'];
$ado = [5, 5, 5];
$dl = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ai = [];
foreach ($ab as $bx => $by) {
$adm[] = $by[0]['label'];
$ado[] = $by[0]['col_width'];
$dl[] = ['data' => $bx, 'renderer' => 'html'];
$ahn = [];
foreach ($by as $k => $du) {
$ai[$du['_rownum']][$bx] = $du['option'];
if ($bx == 'value') {
if (!isset($ai[$du['_rownum']]['id'])) {
$ai[$du['_rownum']]['id'] = $du['id'];
$ai[$du['_rownum']]['oid'] = $kx;
$ai[$du['_rownum']]['_rownum'] = $du['_rownum'];
}
}
}
}
$ai = array_values($ai);
$v['data'] = ['list' => $ai, 'colHeaders' => $adm, 'colWidths' => $ado, 'cols' => $dl];
ret($v);
});
$app->get("/hot/{$bu}_addprop", function () use($afw, $ahm) {
$kx = get('oid');
$adt = get('pid');
$aho = get('propname');
if ($aho != 'value' && !checkOptPropVal($kx, $adt, 'value', $afw, $ahm)) {
addOptProp($kx, $adt, 'value', $afw, $ahm);
}
if (!checkOptPropVal($kx, $adt, $aho, $afw, $ahm)) {
addOptProp($kx, $adt, $aho, $afw, $ahm);
}
ret([11]);
});
$app->options("/hot/{$bu}", function () {
ret([]);
});
$app->options("/hot/{$bu}/{id}", function () {
ret([]);
});
$app->post("/hot/{$bu}", function () use($afw, $ahm) {
$ab = ctx::data();
$adt = $ab['pid'];
$kx = $ab['oid'];
$ahp = getArg($ab, '_rownum');
$ahq = db::row($afw, ['AND' => ['oid' => $kx, 'pid' => $adt, 'name' => 'value']]);
if (!$ahq) {
addOptProp($kx, $adt, 'value', $afw, $ahm);
}
$ahr = $ahq['id'];
$ahs = db::obj()->max($ahm, '_rownum', ['pid' => $ahr]);
$ab = ['oid' => $kx, 'pid' => $ahr, '_rownum' => $ahs + 1];
db::save($ahm, $ab);
$v = ['oid' => $kx, '_rownum' => $ahp, 'prop' => $ahq, 'maxrow' => $ahs];
ret($v);
});
$app->put("/hot/{$bu}/{id}", function ($z, $bo, $my) use($ahm, $afw) {
$ab = ctx::data();
$adt = $ab['pid'];
$kx = $ab['oid'];
$ahp = $ab['_rownum'];
$ahp = getArg($ab, '_rownum');
$aw = $ab['token'];
$be = $ab['uid'];
$du = dissoc($ab, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($du);
$k = key($du);
$u = $du[$k];
$ahq = db::row($afw, ['AND' => ['pid' => $adt, 'oid' => $kx, 'name' => $k]]);
info("{$adt} {$kx} {$k}");
$ahr = $ahq['id'];
$aht = db::obj()->has($ahm, ['AND' => ['pid' => $ahr, '_rownum' => $ahp]]);
if ($aht) {
debug("has cell ...");
$ct = "update {$ahm} set `option`='{$u}' where _rownum={$ahp} and pid={$ahr}";
debug($ct);
db::exec($ct);
} else {
debug("has no cell ...");
$ab = ['oid' => $kx, 'pid' => $ahr, '_rownum' => $ahp, 'option' => $u];
db::save($ahm, $ab);
}
$v = ['item' => $du, 'oid' => $kx, '_rownum' => $ahp, 'key' => $k, 'val' => $u, 'prop' => $ahq, 'sql' => $ct];
ret($v);
});
}
function checkOptPropVal($kx, $adt, $bu, $afw, $ahm)
{
return db::obj()->has($afw, ['AND' => ['name' => $bu, 'oid' => $kx, 'pid' => $adt]]);
}
function addOptProp($kx, $adt, $aho, $afw, $ahm)
{
$bu = Pinyin::get($aho);
$ab = ['oid' => $kx, 'pid' => $adt, 'label' => $aho, 'name' => $bu];
$ahq = db::save($afw, $ab);
$ab = ['_rownum' => 1, 'oid' => $kx, 'pid' => $ahq['id']];
db::save($ahm, $ab);
return $ahq;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$ahu = \cfg::load('mid');
if ($ahu) {
foreach ($ahu as $bx => $m) {
$ahv = "\\{$bx}";
debug("load mid: {$ahv}");
$app->add(new $ahv());
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