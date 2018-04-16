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
$aw = self::$_token;
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
private static $_db_master;
private static $_dbc_list;
private static $_db;
private static $_dbc;
private static $_dbc_master;
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
self::$_dbc_list[$bz] = self::$_dbc;
self::$_db_list[$bz] = self::new_db(self::$_dbc);
if ($by) {
self::use_db($bz);
}
}
public static function use_db($bz)
{
self::$_db = self::$_db_list[$bz];
self::$_dbc = self::$_dbc_list[$bz];
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
public static function switch_dbc($cd)
{
self::$_db = self::$_db_master;
$ce = self::row('corp_instance', ['corpid' => $cd]);
if ($ce) {
$cf = self::row('db_connection', ['id' => $ce['conn_id']]);
$cg = ["database_type" => "mysql", "database_name" => $ce['dbname'], "server" => $cf['host'], "username" => $cf['username'], "password" => $cf['password'], "charset" => "utf8", "debug_mode" => null];
self::$_dbc = $cg;
self::$_db = self::$_db_default = self::new_db(self::$_dbc);
info($cg);
}
}
public static function obj()
{
if (!self::$_db) {
self::$_dbc = self::$_dbc_master = self::get_db_cfg();
self::$_db = self::$_db_default = self::$_db_master = self::new_db(self::$_dbc);
info('====== init dbc =====');
$aw = \ctx::getToken(req());
$ak = \ctx::getUcTokenUser($aw);
$cd = getArg($ak, 'corpid');
if ($cd) {
self::switch_dbc($cd);
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
public static function desc_sql($ch)
{
if (self::db_type() == 'mysql') {
return "desc {$ch}";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$ch}'";
} else {
return '';
}
}
}
public static function table_cols($br)
{
$ci = self::$tbl_desc;
if (!isset($ci[$br])) {
$cj = self::desc_sql($br);
if ($cj) {
$ci[$br] = self::query($cj);
self::$tbl_desc = $ci;
debug("---------------- cache not found : {$br}");
} else {
debug("empty desc_sql for: {$br}");
}
}
if (!isset($ci[$br])) {
return array();
} else {
return self::$tbl_desc[$br];
}
}
public static function col_array($br)
{
$ck = function ($bv) use($br) {
return $br . '.' . $bv;
};
return getKeyValues(self::table_cols($br), 'Field', $ck);
}
public static function valid_table_col($br, $cl)
{
$cm = self::table_cols($br);
foreach ($cm as $cn) {
if ($cn['Field'] == $cl) {
$c = $cn['Type'];
return is_string_column($cn['Type']);
}
}
return false;
}
public static function tbl_data($br, $ab)
{
$cm = self::table_cols($br);
$v = [];
foreach ($cm as $cn) {
$co = $cn['Field'];
if (isset($ab[$co])) {
$v[$co] = $ab[$co];
}
}
return $v;
}
public static function test()
{
$cj = "select * from tags limit 10";
$cp = self::obj()->query($cj)->fetchAll(\PDO::FETCH_ASSOC);
var_dump($cp);
}
public static function has_st($br, $cq)
{
$cr = '_st';
return isset($cq[$cr]) || isset($cq[$br . '.' . $cr]);
}
public static function getWhere($br, $cs)
{
$cr = '_st';
if (!self::valid_table_col($br, $cr)) {
return $cs;
}
$cr = $br . '._st';
if (is_array($cs)) {
$ct = array_keys($cs);
$cu = preg_grep("/^AND\\s*#?\$/i", $ct);
$cv = preg_grep("/^OR\\s*#?\$/i", $ct);
$cw = array_diff_key($cs, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
$cq = [];
if ($cw != array()) {
$cq = $cw;
if (!self::has_st($br, $cq)) {
$cs[$cr] = 1;
$cs = ['AND' => $cs];
}
}
if (!empty($cu)) {
$l = array_values($cu);
$cq = $cs[$l[0]];
if (!self::has_st($br, $cq)) {
$cs[$l[0]][$cr] = 1;
}
}
if (!empty($cv)) {
$l = array_values($cv);
$cq = $cs[$l[0]];
if (!self::has_st($br, $cq)) {
$cs[$l[0]][$cr] = 1;
}
}
if (!isset($cs['AND']) && !self::has_st($br, $cq)) {
$cs['AND'][$cr] = 1;
}
}
return $cs;
}
public static function all_sql($br, $cs = array(), $cx = '*', $cy = null)
{
$cz = [];
if ($cy) {
$cj = self::obj()->selectContext($br, $cz, $cy, $cx, $cs);
} else {
$cj = self::obj()->selectContext($br, $cz, $cx, $cs);
}
return $cj;
}
public static function all($br, $cs = array(), $cx = '*', $cy = null)
{
$cs = self::getWhere($br, $cs);
if ($cy) {
$cp = self::obj()->select($br, $cy, $cx, $cs);
} else {
$cp = self::obj()->select($br, $cx, $cs);
}
return $cp;
}
public static function count($br, $cs = array('_st' => 1))
{
$cs = self::getWhere($br, $cs);
return self::obj()->count($br, $cs);
}
public static function row_sql($br, $cs = array(), $cx = '*', $cy = '')
{
return self::row($br, $cs, $cx, $cy, true);
}
public static function row($br, $cs = array(), $cx = '*', $cy = '', $de = null)
{
$cs = self::getWhere($br, $cs);
if (!isset($cs['LIMIT'])) {
$cs['LIMIT'] = 1;
}
if ($cy) {
if ($de) {
return self::obj()->selectContext($br, $cy, $cx, $cs);
}
$cp = self::obj()->select($br, $cy, $cx, $cs);
} else {
if ($de) {
return self::obj()->selectContext($br, $cx, $cs);
}
$cp = self::obj()->select($br, $cx, $cs);
}
if ($cp) {
return $cp[0];
} else {
return null;
}
}
public static function one($br, $cs = array(), $cx = '*', $cy = '')
{
$df = self::row($br, $cs, $cx, $cy);
$dg = '';
if ($df) {
$dh = array_keys($df);
$dg = $df[$dh[0]];
}
return $dg;
}
public static function parseUk($br, $di, $ab)
{
$dj = true;
info("uk: {$di}, " . json_encode($ab));
if (is_array($di)) {
foreach ($di as $dk) {
if (!isset($ab[$dk])) {
$dj = false;
} else {
$dl[$dk] = $ab[$dk];
}
}
} else {
if (!isset($ab[$di])) {
$dj = false;
} else {
$dl = [$di => $ab[$di]];
}
}
$dm = false;
if ($dj) {
info("has uk {$dj}");
info("where: " . json_encode($dl));
if (!self::obj()->has($br, ['AND' => $dl])) {
$dm = true;
}
} else {
$dm = true;
}
return [$dl, $dm];
}
public static function save($br, $ab, $di = 'id')
{
list($dl, $dm) = self::parseUk($br, $di, $ab);
info("isInsert: {$dm}, {$br} {$di} " . json_encode($ab));
if ($dm) {
debug("insert {$br} : " . json_encode($ab));
self::obj()->insert($br, $ab);
$ab['id'] = self::obj()->id();
} else {
debug("update {$br} " . json_encode($dl));
self::obj()->update($br, $ab, ['AND' => $dl]);
}
return $ab;
}
public static function update($br, $ab, $cs)
{
self::obj()->update($br, $ab, $cs);
}
public static function exec($cj)
{
return self::obj()->query($cj);
}
public static function query($cj)
{
return self::obj()->query($cj)->fetchAll(\PDO::FETCH_ASSOC);
}
public static function queryRow($cj)
{
$cp = self::query($cj);
if ($cp) {
return $cp[0];
} else {
return null;
}
}
public static function queryOne($cj)
{
$df = self::queryRow($cj);
return self::oneVal($df);
}
public static function oneVal($df)
{
$dg = '';
if ($df) {
$dh = array_keys($df);
$dg = $df[$dh[0]];
}
return $dg;
}
public static function updateBatch($br, $ab, $di = 'id')
{
$dn = $br;
if (!is_array($ab) || empty($dn)) {
return FALSE;
}
$cj = "UPDATE `{$dn}` SET";
foreach ($ab as $bs => $df) {
foreach ($df as $k => $u) {
$do[$k][] = "WHEN {$bs} THEN {$u}";
}
}
foreach ($do as $k => $u) {
$cj .= ' `' . trim($k, '`') . '`=CASE ' . $di . ' ' . join(' ', $u) . ' END,';
}
$cj = trim($cj, ',');
$cj .= ' WHERE ' . $di . ' IN(' . join(',', array_keys($ab)) . ')';
return self::query($cj);
}
}
class fcache
{
const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
const CLEAR_ALL_KEY = 'CLEAR_ALL';
static $_instance = null;
protected $_options = array('cache_dir' => './cache', 'file_locking' => true, 'file_name_prefix' => 'cache', 'cache_file_umask' => 0777, 'file_life' => 100000);
static function &getInstance($dp = array())
{
if (self::$_instance === null) {
self::$_instance = new self($dp);
}
return self::$_instance;
}
static function &setOptions($dp = array())
{
return self::getInstance($dp);
}
private function __construct($dp = array())
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
$dq =& self::getInstance();
if (!is_dir($l)) {
exit('file_cache: ' . $l . ' 不是一个有效路径 ');
}
if (!is_writable($l)) {
exit('file_cache: 路径 "' . $l . '" 不可写');
}
$l = rtrim($this->_options['cache_dir'], '/') . '/';
$dq->_options['cache_dir'] = $l;
}
static function save($ab, $bs = null, $dr = null)
{
$dq =& self::getInstance();
if (!$bs) {
if ($dq->_id) {
$bs = $dq->_id;
} else {
exit('file_cache:save() id 不能为空!');
}
}
$ds = time();
if ($dr) {
$ab[self::FILE_LIFE_KEY] = $ds + $dr;
} elseif ($dr != 0) {
$ab[self::FILE_LIFE_KEY] = $ds + $dq->_options['file_life'];
}
$r = $dq->_file($bs);
$ab = "\n" . " // mktime: " . $ds . "\n" . " return " . var_export($ab, true) . "\n?>";
$dt = $dq->_filePutContents($r, $ab);
return $dt;
}
static function load($bs)
{
$dq =& self::getInstance();
$ds = time();
if (!$dq->test($bs)) {
return false;
}
$du = $dq->_file(self::CLEAR_ALL_KEY);
$r = $dq->_file($bs);
if (is_file($du) && filemtime($du) > filemtime($r)) {
return false;
}
$ab = $dq->_fileGetContents($r);
if (empty($ab[self::FILE_LIFE_KEY]) || $ds < $ab[self::FILE_LIFE_KEY]) {
unset($ab[self::FILE_LIFE_KEY]);
return $ab;
}
return false;
}
protected function _filePutContents($r, $dv)
{
$dq =& self::getInstance();
$dw = false;
$dx = @fopen($r, 'ab+');
if ($dx) {
if ($dq->_options['file_locking']) {
@flock($dx, LOCK_EX);
}
fseek($dx, 0);
ftruncate($dx, 0);
$dy = @fwrite($dx, $dv);
if (!($dy === false)) {
$dw = true;
}
@fclose($dx);
}
@chmod($r, $dq->_options['cache_file_umask']);
return $dw;
}
protected function _file($bs)
{
$dq =& self::getInstance();
$dz = $dq->_idToFileName($bs);
return $dq->_options['cache_dir'] . $dz;
}
protected function _idToFileName($bs)
{
$dq =& self::getInstance();
$dq->_id = $bs;
$x = $dq->_options['file_name_prefix'];
$dw = $x . '---' . $bs;
return $dw;
}
static function test($bs)
{
$dq =& self::getInstance();
$r = $dq->_file($bs);
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
$dq =& self::getInstance();
$dq->save('CLEAR_ALL', self::CLEAR_ALL_KEY);
}
static function del($bs)
{
$dq =& self::getInstance();
if (!$dq->test($bs)) {
return false;
}
$r = $dq->_file($bs);
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
$ef = self::obj()->blogs;
$eg = $ef->find()->findAll();
$ab = object2array($eg);
$eh = 1;
foreach ($ab as $bu => $ei) {
unset($ei['_id']);
unset($ei['tid']);
unset($ei['tags']);
if (isset($ei['_intm'])) {
$ei['_intm'] = date('Y-m-d H:i:s', $ei['_intm']['sec']);
}
if (isset($ei['_uptm'])) {
$ei['_uptm'] = date('Y-m-d H:i:s', $ei['_uptm']['sec']);
}
$ei['uid'] = $bc;
$v = db::save('blogs', $ei);
$eh++;
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
self::$_client = $ej = new Predis\Client(cfg::get_redis_cfg());
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
public static function init($ek = array())
{
$m = cfg::get('oauth', 'oauth')['uc'];
$m['host'] = env('UC_HOST', $m['host']);
$m['clientId'] = env('UC_CLIENT_ID', $m['clientId']);
$m['clientSecret'] = env('UC_CLIENT_SECRET', $m['clientSecret']);
$m['redirectUri'] = env('UC_REDIRECT_URI', $m['redirectUri']);
$m['username'] = env('UC_USERNAME', $m['username']);
$m['passwd'] = env('UC_PASSWD', $m['passwd']);
self::$oauth_cfg = $m;
if (isset($ek['host'])) {
self::$UC_HOST = $ek['host'];
}
}
public static function makeUrl($bp, $bd = '')
{
return self::$oauth_cfg['host'] . $bp . ($bd ? '?' . $bd : '');
}
public static function pwd_login($el = null, $em = null, $en = null, $eo = null)
{
$ep = $el ? $el : self::$oauth_cfg['username'];
$eq = $em ? $em : self::$oauth_cfg['passwd'];
$er = $en ? $en : self::$oauth_cfg['clientId'];
$es = $eo ? $eo : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $er, 'client_secret' => $es, 'grant_type' => 'password', 'username' => $ep, 'password' => $eq];
$et = self::makeUrl(self::API['accessToken']);
$eu = curl($et, 10, 30, $ab);
$v = json_decode($eu, true);
self::_set_pwd_user($v);
return $v;
}
public static function get_admin_token($ev = array())
{
if (isset($ev['access_token'])) {
$bg = $ev['access_token'];
} else {
$v = self::pwd_login();
$bg = $v['data']['access_token'];
}
return $bg;
}
public static function id_login($bs, $en = null, $eo = null, $ew = array())
{
$er = $en ? $en : self::$oauth_cfg['clientId'];
$es = $eo ? $eo : self::$oauth_cfg['clientSecret'];
$bg = self::get_admin_token($ew);
$ab = ['client_id' => $er, 'client_secret' => $es, 'grant_type' => 'id', 'access_token' => $bg, 'id' => $bs];
$et = self::makeUrl(self::API['userAccessToken']);
$eu = curl($et, 10, 30, $ab);
$v = json_decode($eu, true);
self::_set_id_user($v);
return $v;
}
public static function authurl($bh, $ex, $bg)
{
$ey = self::$oauth_cfg['host'] . "/api/sso/redirect?access_token={$bg}&app_id={$bh}&domain_id={$ex}";
return $ey;
}
public static function code_login($ez, $fg = null, $en = null, $eo = null)
{
$fh = $fg ? $fg : self::$oauth_cfg['redirectUri'];
$er = $en ? $en : self::$oauth_cfg['clientId'];
$es = $eo ? $eo : self::$oauth_cfg['clientSecret'];
$ab = ['client_id' => $er, 'client_secret' => $es, 'grant_type' => 'authorization_code', 'redirect_uri' => $fh, 'code' => $ez];
$et = self::makeUrl(self::API['accessToken']);
$eu = curl($et, 10, 30, $ab);
$v = json_decode($eu, true);
self::_set_code_user($v);
return $v;
}
public static function user_info($bg)
{
$et = self::makeUrl(self::API['user'], 'access_token=' . $bg);
$eu = curl($et);
$v = json_decode($eu, true);
self::_set_user_info($v);
return $v;
}
public static function reg_user($ep, $em = '123456', $ew = array())
{
$bg = self::get_admin_token($ew);
$ab = ['username' => $ep, 'password' => $em, 'access_token' => $bg];
$et = self::makeUrl(self::API['user']);
$eu = curl($et, 10, 30, $ab);
$fi = json_decode($eu, true);
return $fi;
}
public static function register_user($ep, $em = '123456')
{
return self::reg_user($ep, $em);
}
public static function find_user($ev = array())
{
$bg = self::get_admin_token($ev);
$bd = 'access_token=' . $bg;
if (isset($ev['username'])) {
$bd .= '&username=' . $ev['username'];
}
if (isset($ev['phone'])) {
$bd .= '&phone=' . $ev['phone'];
}
$et = self::makeUrl(self::API['finduser'], $bd);
$eu = curl($et, 10, 30);
$fi = json_decode($eu, true);
return $fi;
}
public static function edit_user($bg, $ab = array())
{
$et = self::makeUrl(self::API['user']);
$ab['access_token'] = $bg;
$ej = new \GuzzleHttp\Client();
$dt = $ej->request('PUT', $et, ['form_params' => $ab, 'headers' => ['X-Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded']]);
$eu = $dt->getBody();
return json_decode($eu, true);
}
public static function set_user_role($bg, $ex, $fj, $fk = 'guest')
{
$ab = ['access_token' => $bg, 'domain_id' => $ex, 'user_id' => $fj, 'role_name' => $fk];
$et = self::makeUrl(self::API['userRole']);
$eu = curl($et, 10, 30, $ab);
return json_decode($eu, true);
}
public static function user_role($bg, $ex)
{
$ab = ['access_token' => $bg, 'domain_id' => $ex];
$et = self::makeUrl(self::API['userRole']);
$et = "{$et}?access_token={$bg}&domain_id={$ex}";
$eu = curl($et, 10, 30);
$v = json_decode($eu, true);
self::_set_user_role($v);
return $v;
}
public static function has_role($fl)
{
if (self::$user_role && isset(self::$user_role['roles'])) {
$fm = self::$user_role['roles'];
foreach ($fm as $k => $fk) {
if ($fk['name'] == $fl) {
return true;
}
}
}
return false;
}
public static function create_domain($fn, $fo, $ew = array())
{
$bg = self::get_admin_token($ew);
$ab = ['access_token' => $bg, 'domain_name' => $fn, 'description' => $fo];
$et = self::makeUrl(self::API['createDomain']);
$eu = curl($et, 10, 30, $ab);
$v = json_decode($eu, true);
self::_set_id_user($v);
return $v;
}
public static function user_domain($bg)
{
$ab = ['access_token' => $bg];
$et = self::makeUrl(self::API['userdomain']);
$et = "{$et}?access_token={$bg}";
$eu = curl($et, 10, 30);
$v = json_decode($eu, true);
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
public static function test($br, $ab)
{
}
public static function registration($ab)
{
$bv = new Valitron\Validator($ab);
$fp = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$bv->rules($fp);
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
public function __invoke($fq, $fr, $fs)
{
log_time("Twig Begin");
$fr = $fs($fq, $fr);
$ft = uripath($fq);
debug(">>>>>> TwigMid START : {$ft}  <<<<<<");
if ($fu = $this->getRoutePath($fq)) {
$bo = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($bo->data);
}
$fv = rtrim($fu, '/');
if ($fv == '/' || !$fv) {
$fv = 'index';
}
$bn = $fv;
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
debug("<<<<<< TwigMid END : {$ft} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $bo->render($fr, tpl($bn), $ab);
} else {
return $fr;
}
}
public function getRoutePath($fq)
{
$fw = \ctx::router()->dispatch($fq);
if ($fw[0] === Dispatcher::FOUND) {
$aj = \ctx::router()->lookupRoute($fw[1]);
$fx = $aj->getPattern();
$fy = new StdParser();
$fz = $fy->parse($fx);
foreach ($fz as $gh) {
foreach ($gh as $dk) {
if (is_string($dk)) {
return $dk;
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
public function __invoke($fq, $fr, $fs)
{
log_time("AuthMid Begin");
$ft = uripath($fq);
debug(">>>>>> AuthMid START : {$ft}  <<<<<<");
\ctx::init($fq);
$this->check_auth($fq, $fr);
debug("<<<<<< AuthMid END : {$ft} >>>>>");
log_time("AuthMid END");
$fr = $fs($fq, $fr);
return $fr;
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
list($gi, $ak, $gj) = $this->auth_cfg();
$ft = uripath($z);
$this->isAjax($ft);
if ($ft == '/') {
return true;
}
$gk = $this->check_list($gi, $ft);
if ($gk) {
$this->check_admin();
}
$gl = $this->check_list($ak, $ft);
if ($gl) {
$this->check_user();
}
$gm = $this->check_list($gj, $ft);
if (!$gm) {
$this->check_user();
}
info("check_auth: {$ft} admin:[{$gk}] user:[{$gl}] pub:[{$gm}]");
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
public function auth_error($gn = 1)
{
$go = is_weixin();
$gp = isMobile();
$gq = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$gn}, is_weixin: {$go} , is_mobile: {$gp}");
$gr = $_SERVER['REQUEST_URI'];
if ($go) {
header("Location: {$gq}/auth/wechat?_r={$gr}");
exit;
}
if ($gp) {
header("Location: {$gq}/auth/openwechat?_r={$gr}");
exit;
}
if ($this->isAjax()) {
ret($gn, 'auth error');
} else {
header('Location: /?_r=' . $gr);
exit;
}
}
public function auth_cfg()
{
$gs = \cfg::get('auth');
return [$gs['admin'], $gs['user'], $gs['public']];
}
public function check_list($ai, $ft)
{
foreach ($ai as $bp) {
if (startWith($ft, $bp)) {
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
public function __invoke($fq, $fr, $fs)
{
$this->init($fq, $fr, $fs);
log_time("{$this->classname} Begin");
$this->path_info = uripath($fq);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($fq, $fr);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$fr = $fs($fq, $fr);
return $fr;
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
public function handlePathArray($gt, $z, $bl)
{
foreach ($gt as $bp => $gu) {
if (startWith($this->path_info, $bp)) {
debug("{$this->path_info} match {$bp} {$gu}");
$this->{$gu}($z, $bl);
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
public function __invoke($fq, $fr, $fs)
{
log_time("RestMid Begin");
$this->path_info = uripath($fq);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($fq)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($fq)) {
$this->apiDoc($fq);
} else {
$this->handelRest($fq);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$fr = $fs($fq, $fr);
return $fr;
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
$gu = $z->getMethod();
info(" method: {$gu}, name: {$br}, id: {$bs}");
$gv = "handle{$gu}";
$this->{$gv}($z, $br, $bs);
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
$gw = \cfg::get('rest_maps', 'rest.yml');
if (isset($gw[$br])) {
$m = $gw[$br][$c];
if ($m) {
$gx = $m['xmap'];
if ($gx) {
$ab = \ctx::data();
foreach ($gx as $bu => $bv) {
unset($ab[$bv]);
}
\ctx::data($ab);
}
}
}
}
public function apiDoc($z)
{
$gy = rd::genApi();
echo $gy;
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
public static function whereStr($cs, $br)
{
$v = '';
foreach ($cs as $bu => $bv) {
$fx = '/(.*)\\{(.*)\\}/i';
$bt = preg_match($fx, $bu, $gz);
$hi = '=';
if ($gz) {
$hj = $gz[1];
$hi = $gz[2];
} else {
$hj = $bu;
}
if ($hk = db::valid_table_col($br, $hj)) {
if ($hk == 2) {
if ($hi == 'in') {
$bv = implode("','", $bv);
$v .= " and t1.{$hj} {$hi} ('{$bv}')";
} else {
$v .= " and t1.{$hj}{$hi}'{$bv}'";
}
} else {
if ($hi == 'in') {
$bv = implode(',', $bv);
$v .= " and t1.{$hj} {$hi} ({$bv})";
} else {
$v .= " and t1.{$hj}{$hi}{$bv}";
}
}
} else {
}
info("[{$br}] [{$hj}] [{$hk}] {$v}");
}
return $v;
}
public static function getSqlFrom($br, $hl, $bc, $hm, $hn, $ew = array())
{
$ho = isset($_GET['tags']) ? 1 : isset($ew['tags']) ? 1 : 0;
$hp = isset($_GET['isar']) ? 1 : 0;
$hq = RestHelper::get_rest_xwh_tags_list();
if ($hq && in_array($br, $hq)) {
$ho = 0;
}
$hr = isset($ew['force_ar']) || RestHelper::isAdmin() && $hp ? "1=1" : "t1.uid={$bc}";
if ($ho) {
$hs = isset($_GET['tags']) ? get('tags') : $ew['tags'];
if ($hs && is_array($hs) && count($hs) == 1 && !$hs[0]) {
$hs = '';
}
$ht = '';
$hu = 'not in';
if ($hs) {
if (is_string($hs)) {
$hs = [$hs];
}
$hv = implode("','", $hs);
$ht = "and `name` in ('{$hv}')";
$hu = 'in';
$hw = " from {$br} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$hl}\n                               where {$hr} and t._st=1  and t.tagid {$hu}\n                               (select id from tags where type='{$br}' {$ht} )\n                               {$hn}";
} else {
$hw = " from {$br} t1\n                              {$hl}\n                              where {$hr} and t1.id not in\n                              (select oid from tag_items where type='{$br}')\n                              {$hn}";
}
} else {
$hx = $hr;
if (RestHelper::isAdmin()) {
if ($br == RestHelper::user_tbl()) {
$hx = "t1.id={$bc}";
}
}
$hw = "from {$br} t1 {$hl} where {$hx} {$hm} {$hn}";
}
return $hw;
}
public static function getSql($br, $ew = array())
{
$bc = RestHelper::uid();
$hy = RestHelper::get('sort', '_intm');
$hz = RestHelper::get('asc', -1);
if (!db::valid_table_col($br, $hy)) {
$hy = '_intm';
}
$hz = $hz > 0 ? 'asc' : 'desc';
$hn = " order by t1.{$hy} {$hz}";
$ij = RestHelper::gets();
$ij = un_select_keys(['sort', 'asc'], $ij);
$ik = RestHelper::get('_st', 1);
$cs = dissoc($ij, ['token', '_st']);
if ($ik != 'all') {
$cs['_st'] = $ik;
}
$hm = self::whereStr($cs, $br);
$il = RestHelper::get('search', '');
$im = RestHelper::get('search-key', '');
if ($il && $im) {
$hm .= " and {$im} like '%{$il}%'";
}
$in = RestHelper::select_add();
$hl = RestHelper::join_add();
$hw = self::getSqlFrom($br, $hl, $bc, $hm, $hn, $ew);
$cj = "select t1.* {$in} {$hw}";
$io = "select count(*) cnt {$hw}";
$ag = RestHelper::offset();
$af = RestHelper::pagesize();
$cj .= " limit {$ag},{$af}";
return [$cj, $io];
}
public static function getResName($br)
{
$ip = RestHelper::get('res_id_key', '');
if ($ip) {
$iq = RestHelper::get($ip);
$br .= '_' . $iq;
}
return $br;
}
public static function getList($br, $ew = array())
{
$bc = RestHelper::uid();
list($cj, $io) = self::getSql($br, $ew);
info($cj);
$cp = db::query($cj);
$ap = (int) db::queryOne($io);
$ir = RestHelper::get_rest_join_tags_list();
if ($ir && in_array($br, $ir)) {
$is = getKeyValues($cp, 'id');
$hs = RestHelper::get_tags_by_oid($bc, $is, $br);
info("get tags ok: {$bc} {$br} " . json_encode($is));
foreach ($cp as $bu => $df) {
if (isset($hs[$df['id']])) {
$it = $hs[$df['id']];
$cp[$bu]['tags'] = getKeyValues($it, 'name');
}
}
info('set tags ok');
}
if (isset($ew['join_cols'])) {
foreach ($ew['join_cols'] as $iu => $iv) {
$iw = getArg($iv, 'jtype', '1-1');
$ix = getArg($iv, 'jkeys', []);
$iy = getArg($iv, 'jwhe', []);
$iz = getArg($iv, 'ast', ['id' => 'ASC']);
if (is_string($iv['on'])) {
$jk = 'id';
$jl = $iv['on'];
} else {
if (is_array($iv['on'])) {
$jm = array_keys($iv['on']);
$jk = $jm[0];
$jl = $iv['on'][$jk];
}
}
$is = getKeyValues($cp, $jk);
$iy[$jl] = $is;
$jn = \db::all($iu, ['AND' => $iy, 'ORDER' => $iz]);
foreach ($jn as $k => $jo) {
foreach ($cp as $bu => &$df) {
if (isset($df[$jk]) && isset($jo[$jl]) && $df[$jk] == $jo[$jl]) {
if ($iw == '1-1') {
foreach ($ix as $jp => $jq) {
$df[$jq] = $jo[$jp];
}
}
$jp = isset($iv['jkey']) ? $iv['jkey'] : $iu;
if ($iw == '1-n') {
$df[$jp][] = $jo[$jp];
}
if ($iw == '1-n-o') {
$df[$jp][] = $jo;
}
if ($iw == '1-1-o') {
$df[$jp] = $jo;
}
}
}
}
}
}
$jr = self::getResName($br);
\ctx::count($ap);
$js = ['pageinfo' => \ctx::pageinfo()];
return ['data' => $cp, 'res-name' => $jr, 'count' => $ap, 'meta' => $js];
}
public static function renderList($br)
{
ret(self::getList($br));
}
public static function getItem($br, $bs)
{
$bc = RestHelper::uid();
info("---GET---: {$br}/{$bs}");
$jr = "{$br}-{$bs}";
if ($br == 'colls') {
$dk = db::row($br, ["{$br}.id" => $bs], ["{$br}.id", "{$br}.title", "{$br}.from_url", "{$br}._intm", "{$br}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($br == 'feeds') {
$c = RestHelper::get('type');
$jt = RestHelper::get('rid');
$dk = db::row($br, ['AND' => ['uid' => $bc, 'rid' => $bs, 'type' => $c]]);
if (!$dk) {
$dk = ['rid' => $bs, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$jr = "{$jr}-{$c}-{$bs}";
} else {
$dk = db::row($br, ['id' => $bs]);
}
}
if ($ju = RestHelper::rest_extra_data()) {
$dk = array_merge($dk, $ju);
}
return ['data' => $dk, 'res-name' => $jr, 'count' => 1];
}
public static function renderItem($br, $bs)
{
ret(self::getItem($br, $bs));
}
public static function postData($br)
{
$ab = db::tbl_data($br, RestHelper::data());
$bc = RestHelper::uid();
$hs = [];
if ($br == 'tags') {
$hs = RestHelper::get_tag_by_name($bc, $ab['name'], $ab['type']);
}
if ($hs && $br == 'tags') {
$ab = $hs[0];
} else {
info("---POST---: {$br} " . json_encode($ab));
unset($ab['token']);
$ab['_intm'] = date('Y-m-d H:i:s');
if (!isset($ab['uid'])) {
$ab['uid'] = $bc;
}
$ab = db::tbl_data($br, $ab);
$ab = db::save($br, $ab);
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
$bc = RestHelper::uid();
$ab = RestHelper::data();
unset($ab['token']);
unset($ab['uniqid']);
self::checkOwner($br, $bs, $bc);
if (isset($ab['inc'])) {
$jv = $ab['inc'];
unset($ab['inc']);
db::exec("UPDATE {$br} SET {$jv} = {$jv} + 1 WHERE id={$bs}");
}
if (isset($ab['dec'])) {
$jv = $ab['dec'];
unset($ab['dec']);
db::exec("UPDATE {$br} SET {$jv} = {$jv} - 1 WHERE id={$bs}");
}
if (isset($ab['tags'])) {
RestHelper::del_tag_by_name($bc, $bs, $br);
$hs = $ab['tags'];
foreach ($hs as $jw) {
$jx = RestHelper::get_tag_by_name($bc, $jw, $br);
if ($jx) {
$jy = $jx[0]['id'];
RestHelper::save_tag_items($bc, $jy, $bs, $br);
}
}
}
info("---PUT---: {$br}/{$bs} " . json_encode($ab));
$ab = db::tbl_data($br, RestHelper::data());
$ab['id'] = $bs;
db::save($br, $ab);
return $ab;
}
public static function renderPutData($br, $bs)
{
$ab = self::putData($br, $bs);
ret($ab);
}
public static function delete($z, $br, $bs)
{
$bc = RestHelper::uid();
self::checkOwner($br, $bs, $bc);
db::save($br, ['_st' => 0, 'id' => $bs]);
ret([]);
}
public static function checkOwner($br, $bs, $bc)
{
$cs = ['AND' => ['id' => $bs], 'LIMIT' => 1];
$cp = db::obj()->select($br, '*', $cs);
if ($cp) {
$dk = $cp[0];
} else {
$dk = null;
}
if ($dk) {
if (array_key_exists('uid', $dk)) {
$jz = $dk['uid'];
if ($br == RestHelper::user_tbl()) {
$jz = $dk['id'];
}
if ($jz != $bc && (!RestHelper::isAdmin() || !RestHelper::isAdminRest())) {
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
class Tagx
{
public static $tbl_name = 'tags';
public static $tbl_items_name = 'tag_items';
public static function getTagByName($bc, $jw, $c)
{
$hs = \db::all(self::$tbl_name, ['AND' => ['uid' => $bc, 'name' => $jw, 'type' => $c, '_st' => 1]]);
return $hs;
}
public static function delTagByOid($bc, $kl, $km)
{
info("del tag: {$bc}, {$kl}, {$km}");
$v = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $bc, 'oid' => $kl, 'type' => $km]]);
info($v);
}
public static function saveTagItems($bc, $kn, $kl, $km)
{
\db::save('tag_items', ['tagid' => $kn, 'uid' => $bc, 'oid' => $kl, 'type' => $km, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($bc, $c)
{
$hs = \db::all(self::$tbl_name, ['AND' => ['uid' => $bc, 'type' => $c, '_st' => 1]]);
return $hs;
}
public static function getTagsByOid($bc, $kl, $c)
{
$cj = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$kl} and t2.type='{$c}' and t2._st=1";
$cp = \db::query($cj);
return getKeyValues($cp, 'name');
}
public static function getTagsByOids($bc, $ko, $c)
{
if (is_array($ko)) {
$ko = implode(',', $ko);
}
$cj = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$ko}) and t2.type='{$c}' and t2._st=1";
$cp = \db::query($cj);
$ab = groupArray($cp, 'oid');
return $ab;
}
public static function countByTag($bc, $jw, $c)
{
$cj = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$jw}' and t1.type='{$c}' and t1.uid={$bc}";
$cp = \db::query($cj);
return [$cp[0]['cnt'], $cp[0]['id']];
}
public static function saveTag($bc, $jw, $c)
{
$ab = ['uid' => $bc, 'name' => $jw, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$ab = \db::save('tags', $ab);
return $ab;
}
public static function countTags($bc, $kp, $br)
{
foreach ($kp as $jw) {
list($kq, $bs) = self::countByTag($bc, $jw, $br);
echo "{$jw} {$kq} {$bs} <br>";
\db::update('tags', ['count' => $kq], ['id' => $bs]);
}
}
public static function saveRepoTags($bc, $kr)
{
$br = 'stars';
echo count($kr) . "<br>";
$kp = [];
foreach ($kr as $ks) {
$kt = $ks['repoId'];
$hs = isset($ks['tags']) ? $ks['tags'] : [];
if ($hs) {
foreach ($hs as $jw) {
if (!in_array($jw, $kp)) {
$kp[] = $jw;
}
$hs = self::getTagByName($bc, $jw, $br);
if (!$hs) {
$jx = self::saveTag($bc, $jw, $br);
} else {
$jx = $hs[0];
}
$kn = $jx['id'];
$ku = getStarByRepoId($bc, $kt);
if ($ku) {
$kl = $ku[0]['id'];
$kv = self::getTagsByOid($bc, $kl, $br);
if ($jx && !in_array($jw, $kv)) {
self::saveTagItems($bc, $kn, $kl, $br);
}
} else {
echo "-------- star for {$kt} not found <br>";
}
}
} else {
}
}
self::countTags($bc, $kp, $br);
}
public static function getTagItem($kw, $bc, $kx, $di, $ky)
{
$cj = "select * from {$kx} where {$di}={$ky} and uid={$bc}";
return $kw->query($cj)->fetchAll();
}
public static function saveItemTags($kw, $bc, $br, $kz, $di = 'id')
{
echo count($kz) . "<br>";
$kp = [];
foreach ($kz as $lm) {
$ky = $lm[$di];
$hs = isset($lm['tags']) ? $lm['tags'] : [];
if ($hs) {
foreach ($hs as $jw) {
if (!in_array($jw, $kp)) {
$kp[] = $jw;
}
$hs = getTagByName($kw, $bc, $jw, $br);
if (!$hs) {
$jx = saveTag($kw, $bc, $jw, $br);
} else {
$jx = $hs[0];
}
$kn = $jx['id'];
$ku = getTagItem($kw, $bc, $br, $di, $ky);
if ($ku) {
$kl = $ku[0]['id'];
$kv = getTagsByOid($kw, $bc, $kl, $br);
if ($jx && !in_array($jw, $kv)) {
saveTagItems($kw, $bc, $kn, $kl, $br);
}
} else {
echo "-------- star for {$ky} not found <br>";
}
}
} else {
}
}
countTags($kw, $bc, $kp, $br);
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
public function __construct($ln = '')
{
if ($ln) {
$this->service = $ln;
$ew = self::$_services[$this->service];
$lo = $ew['url'];
debug("init client: {$lo}");
$this->client = new Client(['base_uri' => $lo, 'timeout' => 12.0]);
}
}
public static function add($ew = array())
{
if ($ew) {
$br = $ew['name'];
if (!isset(self::$_services[$br])) {
self::$_services[$br] = $ew;
}
}
}
public static function init()
{
$lp = \cfg::get('service_list', 'service');
foreach ($lp as $m) {
self::add($m);
}
}
public function getRest($ln, $x = '/rest')
{
return $this->get($ln, $x . '/');
}
public function get($ln, $x = '')
{
if (isset(self::$_services[$ln])) {
if (!isset(self::$_ins[$ln])) {
self::$_ins[$ln] = new Service($ln);
}
}
if (isset(self::$_ins[$ln])) {
$lq = self::$_ins[$ln];
if ($x) {
$lq->setPrefix($x);
}
return $lq;
} else {
return null;
}
}
public function setPrefix($x)
{
$this->prefix = $x;
}
public function __call($lr, $ls)
{
$ew = self::$_services[$this->service];
$lo = $ew['url'];
$bh = $ew['appid'];
$be = $ew['appkey'];
$ab = $ls[0];
$ab = array_merge($ab, $_GET);
$ab['appid'] = $bh;
$ab['date'] = date("Y-m-d H:i:s");
$ab['sign'] = gen_sign($ab, $be);
$gu = getArg($ls, 1, 'GET');
$lt = getArg($ls, 2, '');
$lr = $this->prefix . $lr . $lt;
debug("api_url: {$bh} {$be} {$lo}");
debug("api_name: {$lr} {$gu}");
debug("data: " . json_encode($ab));
try {
$this->resp = $this->client->request($gu, $lr, ['form_params' => $ab]);
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
public function __get($lu)
{
$gu = 'get' . ucfirst($lu);
if (method_exists($this, $gu)) {
$lv = new ReflectionMethod($this, $gu);
if (!$lv->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $lu)) {
return $this->{$lu};
}
}
public function __set($lu, $l)
{
$gu = 'set' . ucfirst($lu);
if (method_exists($this, $gu)) {
$lv = new ReflectionMethod($this, $gu);
if (!$lv->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $lu)) {
$this->{$lu} = $l;
}
}
}
}
namespace {
if (getenv('WHOOPS_ENABLED') == 'yes') {
$lw = new \Whoops\Run();
$lw->pushHandler(new \Whoops\Handler\PrettyPageHandler());
$lw->register();
}
function getCaller($lx = NULL)
{
$ly = debug_backtrace();
$lz = $ly[2];
if (isset($lx)) {
return $lz[$lx];
} else {
return $lz;
}
}
function getCallerStr($mn = 4)
{
$ly = debug_backtrace();
$lz = $ly[2];
$mo = $ly[1];
$mp = $lz['function'];
$mq = isset($lz['class']) ? $lz['class'] : '';
$mr = $mo['file'];
$ms = $mo['line'];
if ($mn == 4) {
$bt = "{$mq} {$mp} {$mr} {$ms}";
} elseif ($mn == 3) {
$bt = "{$mq} {$mp} {$ms}";
} else {
$bt = "{$mq} {$ms}";
}
return $bt;
}
function wlog($bp, $mt, $mu)
{
if (is_dir($bp)) {
$mv = date('Y-m-d', time());
$mu .= "\n";
file_put_contents($bp . "/{$mt}-{$mv}.log", $mu, FILE_APPEND);
}
}
function folder_exist($mw)
{
$bp = realpath($mw);
return ($bp !== false and is_dir($bp)) ? $bp : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($ab, $mx)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$my = $m['symmetric_key'];
$mz = $m['hmac_key'];
$no = new AES_SHA($my, $mz);
return $no->encrypt(serialize($ab), $mx);
}
function decrypt($ab)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $ab;
}
$my = $m['symmetric_key'];
$mz = $m['hmac_key'];
$no = new AES_SHA($my, $mz);
return unserialize($no->decrypt($ab));
}
function encrypt_cookie($np)
{
return encrypt($np->getData(), $np->getExpiration());
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($dv, $nq = 'DECODE', $k = '', $nr = 0)
{
$ns = 4;
$k = md5($k ? $k : UC_KEY);
$nt = md5(substr($k, 0, 16));
$nu = md5(substr($k, 16, 16));
$nv = $ns ? $nq == 'DECODE' ? substr($dv, 0, $ns) : substr(md5(microtime()), -$ns) : '';
$nw = $nt . md5($nt . $nv);
$nx = strlen($nw);
$dv = $nq == 'DECODE' ? base64_decode(substr($dv, $ns)) : sprintf('%010d', $nr ? $nr + time() : 0) . substr(md5($dv . $nu), 0, 16) . $dv;
$ny = strlen($dv);
$dw = '';
$nz = range(0, 255);
$op = array();
for ($eh = 0; $eh <= 255; $eh++) {
$op[$eh] = ord($nw[$eh % $nx]);
}
for ($oq = $eh = 0; $eh < 256; $eh++) {
$oq = ($oq + $nz[$eh] + $op[$eh]) % 256;
$dy = $nz[$eh];
$nz[$eh] = $nz[$oq];
$nz[$oq] = $dy;
}
for ($or = $oq = $eh = 0; $eh < $ny; $eh++) {
$or = ($or + 1) % 256;
$oq = ($oq + $nz[$or]) % 256;
$dy = $nz[$or];
$nz[$or] = $nz[$oq];
$nz[$oq] = $dy;
$dw .= chr(ord($dv[$eh]) ^ $nz[($nz[$or] + $nz[$oq]) % 256]);
}
if ($nq == 'DECODE') {
if ((substr($dw, 0, 10) == 0 || substr($dw, 0, 10) - time() > 0) && substr($dw, 10, 16) == substr(md5(substr($dw, 26) . $nu), 0, 16)) {
return substr($dw, 26);
} else {
return '';
}
} else {
return $nv . str_replace('=', '', base64_encode($dw));
}
}

function object2array(&$os)
{
$os = json_decode(json_encode($os), true);
return $os;
}
function getKeyValues($ab, $k, $ck = null)
{
if (!$ck) {
$ck = function ($bv) {
return $bv;
};
}
$ot = array();
if ($ab && is_array($ab)) {
foreach ($ab as $dk) {
if (isset($dk[$k]) && $dk[$k]) {
$u = $dk[$k];
if ($ck) {
$u = $ck($u);
}
$ot[] = $u;
}
}
}
return array_unique($ot);
}
if (!function_exists('indexArray')) {
function indexArray($ab, $k, $ev = null)
{
$ot = array();
if ($ab && is_array($ab)) {
foreach ($ab as $dk) {
if (!isset($dk[$k]) || !$dk[$k] || !is_scalar($dk[$k])) {
continue;
}
if (!$ev) {
$ot[$dk[$k]] = $dk;
} else {
if (is_string($ev)) {
$ot[$dk[$k]] = $dk[$ev];
} else {
if (is_array($ev)) {
$ou = [];
foreach ($ev as $bu => $bv) {
$ou[$bv] = $dk[$bv];
}
$ot[$dk[$k]] = $dk[$ev];
}
}
}
}
}
return $ot;
}
}
if (!function_exists('groupArray')) {
function groupArray($ov, $k)
{
if (!is_array($ov) || !$ov) {
return array();
}
$ab = array();
foreach ($ov as $dk) {
if (isset($dk[$k]) && $dk[$k]) {
$ab[$dk[$k]][] = $dk;
}
}
return $ab;
}
}
function select_keys($dh, $ab)
{
$v = [];
foreach ($dh as $k) {
if (isset($ab[$k])) {
$v[$k] = $ab[$k];
} else {
$v[$k] = '';
}
}
return $v;
}
function un_select_keys($dh, $ab)
{
$v = [];
foreach ($ab as $bu => $dk) {
if (!in_array($bu, $dh)) {
$v[$bu] = $dk;
}
}
return $v;
}
function copyKey($ab, $ow, $ox)
{
foreach ($ab as &$dk) {
$dk[$ox] = $dk[$ow];
}
return $ab;
}
function addKey($ab, $k, $u)
{
foreach ($ab as &$dk) {
$dk[$k] = $u;
}
return $ab;
}
function dissoc($ov, $dh)
{
if (is_array($dh)) {
foreach ($dh as $k) {
unset($ov[$k]);
}
} else {
unset($ov[$dh]);
}
return $ov;
}
function insertAt($oy, $oz, $l)
{
array_splice($oy, $oz, 0, [$l]);
return $oy;
}
function getArg($pq, $pr, $ps = '')
{
if (isset($pq[$pr])) {
return $pq[$pr];
} else {
return $ps;
}
}
function permu($au, $cy = ',')
{
$ai = [];
if (is_string($au)) {
$pt = str_split($au);
} else {
$pt = $au;
}
sort($pt);
$pu = count($pt) - 1;
$pv = $pu;
$ap = 1;
$dk = implode($cy, $pt);
$ai[] = $dk;
while (true) {
$pw = $pv--;
if ($pt[$pv] < $pt[$pw]) {
$px = $pu;
while ($pt[$pv] > $pt[$px]) {
$px--;
}

list($pt[$pv], $pt[$px]) = array($pt[$px], $pt[$pv]);

for ($eh = $pu; $eh > $pw; $eh--, $pw++) {
list($pt[$eh], $pt[$pw]) = array($pt[$pw], $pt[$eh]);
}
$dk = implode($cy, $pt);
$ai[] = $dk;
$pv = $pu;
$ap++;
}
if ($pv == 0) {
break;
}
}
return $ai;
}
function combin($ot, $py, $pz = ',')
{
$dw = array();
if ($py == 1) {
return $ot;
}
if ($py == count($ot)) {
$dw[] = implode($pz, $ot);
return $dw;
}
$qr = $ot[0];
unset($ot[0]);
$ot = array_values($ot);
$qs = combin($ot, $py - 1, $pz);
foreach ($qs as $qt) {
$qt = $qr . $pz . $qt;
$dw[] = $qt;
}
unset($qs);
$qu = combin($ot, $py, $pz);
foreach ($qu as $qt) {
$dw[] = $qt;
}
unset($qu);
return $dw;
}
function getExcelCol($cl)
{
$ot = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($cl == 0) {
return '';
}
return getExcelCol((int) (($cl - 1) / 26)) . $ot[$cl % 26];
}
function getExcelPos($df, $cl)
{
return getExcelCol($cl) . $df;
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
function succ($ot = array(), $qv = 'succ', $qw = 1)
{
$ab = $ot;
$qx = 0;
$qy = 1;
$ap = 0;
$v = array($qv => $qw, 'errormsg' => '', 'errorfield' => '');
if (isset($ot['data'])) {
$ab = $ot['data'];
}
$v['data'] = $ab;
if (isset($ot['total_page'])) {
$v['total_page'] = $ot['total_page'];
}
if (isset($ot['cur_page'])) {
$v['cur_page'] = $ot['cur_page'];
}
if (isset($ot['count'])) {
$v['count'] = $ot['count'];
}
if (isset($ot['res-name'])) {
$v['res-name'] = $ot['res-name'];
}
if (isset($ot['meta'])) {
$v['meta'] = $ot['meta'];
}
sendJSON($v);
}
function fail($ot = array(), $qv = 'succ', $qz = 0)
{
$k = $mu = '';
if (count($ot) > 0) {
$dh = array_keys($ot);
$k = $dh[0];
$mu = $ot[$k][0];
}
$v = array($qv => $qz, 'errormsg' => $mu, 'errorfield' => $k);
sendJSON($v);
}
function code($ot = array(), $ez = 0)
{
if (is_string($ez)) {
}
if ($ez == 0) {
succ($ot, 'code', 0);
} else {
fail($ot, 'code', $ez);
}
}
function ret($ot = array(), $ez = 0, $jv = '')
{
$or = $ot;
$rs = $ez;
if (is_numeric($ot) || is_string($ot)) {
$rs = $ot;
$or = array();
if (is_array($ez)) {
$or = $ez;
} else {
$ez = $ez === 0 ? '' : $ez;
$or = array($jv => array($ez));
}
}
code($or, $rs);
}
function err($rt)
{
code($rt, 1);
}
function downloadExcel($ru, $dz)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $dz . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$ru->save('php://output');
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
function curl($et, $rv = 10, $rw = 30, $rx = '', $gu = 'post')
{
$ry = curl_init($et);
curl_setopt($ry, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ry, CURLOPT_CONNECTTIMEOUT, $rv);
curl_setopt($ry, CURLOPT_HEADER, 0);
curl_setopt($ry, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($ry, CURLOPT_TIMEOUT, $rw);
if (file_exists(cacert_file())) {
curl_setopt($ry, CURLOPT_CAINFO, cacert_file());
}
if ($rx) {
if (is_array($rx)) {
$rx = http_build_query($rx);
}
if ($gu == 'post') {
curl_setopt($ry, CURLOPT_POST, 1);
} else {
if ($gu == 'put') {
curl_setopt($ry, CURLOPT_CUSTOMREQUEST, "put");
}
}
curl_setopt($ry, CURLOPT_POSTFIELDS, $rx);
}
$dw = curl_exec($ry);
if (curl_errno($ry)) {
return '';
}
curl_close($ry);
return $dw;
}
function curl_header($et, $rv = 10, $rw = 30)
{
$ry = curl_init($et);
curl_setopt($ry, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ry, CURLOPT_CONNECTTIMEOUT, $rv);
curl_setopt($ry, CURLOPT_HEADER, 1);
curl_setopt($ry, CURLOPT_NOBODY, 1);
curl_setopt($ry, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($ry, CURLOPT_TIMEOUT, $rw);
if (file_exists(cacert_file())) {
curl_setopt($ry, CURLOPT_CAINFO, cacert_file());
}
$dw = curl_exec($ry);
if (curl_errno($ry)) {
return '';
}
return $dw;
}

function startWith($bt, $qt)
{
return strpos($bt, $qt) === 0;
}
function endWith($rz, $st)
{
$su = strlen($st);
if ($su == 0) {
return true;
}
return substr($rz, -$su) === $st;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $ab, $sv = false, $jv = '')
{
$ov = getKeyValues($ab, $k);
if (!$ov) {
return '';
}
if ($sv) {
foreach ($ov as $bu => $bv) {
$ov[$bu] = "'{$bv}'";
}
}
$bt = implode(',', $ov);
if ($jv) {
$k = $jv;
}
return " {$k} in ({$bt})";
}
function get_top_domain($et)
{
$fx = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($fx, $et, $sw);
if (count($sw) > 0) {
return $sw[0];
} else {
$sx = parse_url($et);
$sy = $sx["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($sy))), $sy)) {
return $sy;
} else {
$ot = explode(".", $sy);
$ap = count($ot);
$sz = array("com", "net", "org", "3322");
if (in_array($ot[$ap - 2], $sz)) {
$gq = $ot[$ap - 3] . "." . $ot[$ap - 2] . "." . $ot[$ap - 1];
} else {
$gq = $ot[$ap - 2] . "." . $ot[$ap - 1];
}
return $gq;
}
}
}
function genID($mo)
{
list($tu, $tv) = explode(" ", microtime());
$tw = rand(0, 100);
return $mo . $tv . substr($tu, 2, 6);
}
function cguid($tx = false)
{
mt_srand((double) microtime() * 10000);
$ty = md5(uniqid(rand(), true));
return $tx ? strtoupper($ty) : $ty;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$tz = cguid();
$uv = chr(45);
$uw = chr(123) . substr($tz, 0, 8) . $uv . substr($tz, 8, 4) . $uv . substr($tz, 12, 4) . $uv . substr($tz, 16, 4) . $uv . substr($tz, 20, 12) . chr(125);
return $uw;
}
}
function randstr($kq = 6)
{
return substr(md5(rand()), 0, $kq);
}
function hashsalt($eq, $ux = '')
{
$ux = $ux ? $ux : randstr(10);
$uy = md5(md5($eq) . $ux);
return [$uy, $ux];
}
function gen_letters($kq = 26)
{
$qt = '';
for ($eh = 65; $eh < 65 + $kq; $eh++) {
$qt .= strtolower(chr($eh));
}
return $qt;
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
$uz = '';
foreach ($bd as $k => $u) {
$uz .= $k . (is_array($u) ? assemble($u) : $u);
}
return $uz;
}
function check_sign($bd, $aw = null)
{
$uz = getArg($bd, 'sign');
$vw = getArg($bd, 'date');
$vx = strtotime($vw);
$vy = time();
$vz = $vy - $vx;
debug("check_sign : {$vy} - {$vx} = {$vz}");
if (!$vw || $vy - $vx > 60) {
debug("check_sign fail : {$vw} delta > 60");
return false;
}
unset($bd['sign']);
$wx = gen_sign($bd, $aw);
debug("{$uz} -- {$wx}");
return $uz == $wx;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$wy = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$wy = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$wy = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$wy = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$wy = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$wy = getenv("REMOTE_ADDR");
} else {
$wy = "Unknown";
}
}
}
}
}
}
return $wy;
}
function getRIP()
{
$wy = $_SERVER["REMOTE_ADDR"];
return $wy;
}
function env($k = 'DEV_MODE', $ps = '')
{
$l = getenv($k);
return $l ? $l : $ps;
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
$wz = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $wz) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
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
function cache($k, $ck = null, $tv = 10, $xy = 0)
{
$xz = new FilesystemCache();
if ($ck) {
if (is_callable($ck)) {
if ($xy || !$xz->has($k)) {
$ab = $ck();
debug("--------- fn: no cache for [{$k}] ----------");
$xz->set($k, $ab, $tv);
} else {
$ab = $xz->get($k);
debug("======= fn: data from cache [{$k}] ========");
}
} else {
debug("--------- set cache for [{$k}] ---------- : " . json_encode($ck));
$xz->set($k, $ck, $tv);
$ab = $ck;
}
} else {
debug("--------- get cache for [{$k}] ---------- ");
$ab = $xz->get($k);
}
return $ab;
}
function cache_del($k)
{
$xz = new FilesystemCache();
$xz->delete($k);
debug("!!!!!!--------- delete cache for [{$k}] ----------!!!!!!");
}
function cache_clear()
{
$xz = new FilesystemCache();
$xz->clear();
debug("!!!!!!--------- clear all cache ----------!!!!!!");
}
function baseModel($yz)
{
return <<<EOF


namespace Entities {
class {$yz}
{
    protected \$id;
    protected \$intm;
    protected \$st;


EOF;
    
}
function baseArray($yz, $dn)
{
return array("Entities\\{$yz}" => array('type' => 'entity', 'table' => $dn, 'id' => array('id' => array('type' => 'integer', 'id' => true, 'generator' => array('strategy' => 'IDENTITY'))), 'fields' => array('intm' => array('type' => 'datetime', 'column' => '_intm'), 'st' => array('type' => 'integer', 'column' => '_st', 'options' => array('default' => 1)))));
}
function genObj($yz)
{
$abc = array_merge(\db::col_array('sys_objects'), ['sys_object_item.name(itemname)', 'sys_object_item.colname', 'sys_object_item.type', 'sys_object_item.length', 'sys_object_item.default', 'sys_object_item.comment']);
$cy = ['[>]sys_object_item' => ['id' => 'oid']];
$dl = ['AND' => ['sys_objects.name' => $yz], 'ORDER' => ['sys_objects.id' => 'DESC']];
$cp = \db::all('sys_objects', $dl, $abc, $cy);
if ($cp) {
$dn = $cp[0]['table'];
$ab = baseArray($yz, $dn);
$abd = baseModel($yz);
foreach ($cp as $df) {
if (!$df['itemname']) {
continue;
}
$abe = $df['colname'] ? $df['colname'] : $df['itemname'];
$jv = ['type' => "{$df['type']}", 'column' => "{$abe}", 'options' => array('default' => "{$df['default']}", 'comment' => "{$df['comment']}")];
$ab['Entities\\' . $yz]['fields'][$df['itemname']] = $jv;
$abd .= "    protected \${$df['itemname']}; \n";
}
$abd .= '}}';
}
return [$ab, $abd];
}
function writeObjFile($yz)
{
list($ab, $abd) = genObj($yz);
$abf = \Symfony\Component\Yaml\Yaml::dump($ab);
$abg = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$abh = $abg . '/src/objs';
if (!is_dir($abh)) {
mkdir($abh);
}
file_put_contents("{$abh}/{$yz}.php", $abd);
file_put_contents("{$abh}/Entities.{$yz}.dcm.yml", $abf);
}
function sync_to_db($abi = 'run')
{
echo $abi;
$abg = ROOT_PATH . env('ORM_PATH', '/tools/bin/db');
$abi = "cd {$abg} && sh ./{$abi}.sh";
exec($abi, $ov);
foreach ($ov as $dk) {
echo \SqlFormatter::format($dk);
}
}
function gen_schema($abj, $abk, $abl = false, $abm = false)
{
$abn = true;
$abo = ROOT_PATH . '/tools/bin/db';
$abp = [$abo . "/yml", $abo . "/src/objs"];
$e = \Doctrine\ORM\Tools\Setup::createYAMLMetadataConfiguration($abp, $abn);
$abq = \Doctrine\ORM\EntityManager::create($abj, $e);
$abr = $abq->getConnection()->getDatabasePlatform();
$abr->registerDoctrineTypeMapping('enum', 'string');
$abs = [];
foreach ($abk as $abt) {
$abu = $abt['name'];
include_once "{$abo}/src/objs/{$abu}.php";
$abs[] = $abq->getClassMetadata('Entities\\' . $abu);
}
$abv = new \Doctrine\ORM\Tools\SchemaTool($abq);
$abw = $abv->getUpdateSchemaSql($abs, true);
if (!$abw) {
echo "Nothing to do.";
}
$abx = [];
foreach ($abw as $dk) {
if (startWith($dk, 'DROP')) {
$abx[] = $dk;
}
echo \SqlFormatter::format($dk);
}
if ($abl && !$abx || $abm) {
$v = $abv->updateSchema($abs, true);
}
}
function gen_corp_schema($cd, $abk)
{
\db::switch_dbc($cd);
$aby = \db::dbc();
$abj = ['driver' => 'pdo_mysql', 'host' => $aby['server'], 'user' => $aby['username'], 'password' => $aby['password'], 'dbname' => $aby['database_name']];
echo "Gen Schema for : {$aby['database_name']} <br>";
$abl = get('write', false);
$abz = get('force', false);
gen_schema($abj, $abk, $abl, $abz);
}
function buildcmd($ew = array())
{
$acd = new ptlis\ShellCommand\CommandBuilder();
$pq = ['LC_CTYPE=en_US.UTF-8'];
if (isset($ew['args'])) {
$pq = $ew['args'];
}
if (isset($ew['add_args'])) {
$pq = array_merge($pq, $ew['add_args']);
}
$ace = $acd->setCommand('/usr/bin/env')->addArguments($pq)->buildCommand();
return $ace;
}
function exec_git($ew = array())
{
$bp = '.';
if (isset($ew['path'])) {
$bp = $ew['path'];
}
$pq = ["/usr/bin/git", "--git-dir={$bp}/.git", "--work-tree={$bp}"];
$abi = 'status';
if (isset($ew['cmd'])) {
$abi = $ew['cmd'];
}
$pq[] = $abi;
$ace = buildcmd(['add_args' => $pq, $abi]);
$dw = $ace->runSynchronous();
return $dw->getStdOutLines();
}
use db\Rest as rest;
function getMetaData($yz, $acf = array())
{
ctx::pagesize(50);
$abk = db::all('sys_objects');
$acg = array_filter($abk, function ($bv) use($yz) {
return $bv['name'] == $yz;
});
$acg = array_shift($acg);
$ach = $acg['id'];
$aci = db::all('sys_object_item', ['oid' => $ach]);
$acj = ['Id'];
$ack = [0.1];
$cx = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($aci as $dk) {
$br = $dk['name'];
$abe = $dk['colname'] ? $dk['colname'] : $br;
$c = $dk['type'];
$ps = $dk['default'];
$acl = $dk['col_width'];
$acm = $dk['readonly'] ? ture : false;
$acn = $dk['is_meta'];
if ($acn) {
$acj[] = $br;
$ack[] = (double) $acl;
if (in_array($abe, array_keys($acf))) {
$cx[] = $acf[$abe];
} else {
$cx[] = ['data' => $abe, 'renderer' => 'html', 'readOnly' => $acm];
}
}
}
$acj[] = "InTm";
$acj[] = "St";
$ack[] = 60;
$ack[] = 10;
$cx[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$cx[] = ['data' => "_st", 'renderer' => "html"];
$js = ['objname' => $yz];
return [$js, $acj, $ack, $cx];
}
function getHotData($yz, $acf = array())
{
$acj[] = "InTm";
$acj[] = "St";
$ack[] = 60;
$ack[] = 10;
$cx[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$cx[] = ['data' => "_st", 'renderer' => "html"];
$js = ['objname' => $yz];
return [$js, $acj, $ack, $cx];
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
function idxtree($aco, $acp)
{
$is = [];
$ab = \db::all($aco, ['pid' => $acp]);
$acq = getKeyValues($ab, 'id');
if ($acq) {
foreach ($acq as $acp) {
$is = array_merge($is, idxtree($aco, $acp));
}
}
return array_merge($acq, $is);
}
function treelist($aco, $acp)
{
$acr = \db::row($aco, ['id' => $acp]);
$acs = $acr['sub_ids'];
$acs = json_decode($acs, true);
$act = \db::all($aco, ['id' => $acs]);
$acu = 0;
foreach ($act as $bu => $acv) {
if ($acv['pid'] == $acp) {
$act[$bu]['pid'] = 0;
$acu++;
}
}
if ($acu < 2) {
$act[] = [];
}
return $act;
return array_merge([$acr], $act);
}
function auto_reg_user($acw = 'username', $acx = 'password', $ch = 'user', $acy = 0)
{
$acz = randstr(10);
$eq = randstr(6);
$ab = ["{$acw}" => $acz, "{$acx}" => $eq, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($acy) {
list($eq, $ux) = hashsalt($eq);
$ab[$acx] = $eq;
$ab['salt'] = $ux;
} else {
$ab[$acx] = md5($eq);
}
return db::save($ch, $ab);
}
function refresh_token($ch, $bc, $gq = '')
{
$ade = cguid();
$ab = ['id' => $bc, 'token' => $ade];
$ak = db::save($ch, $ab);
if ($gq) {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/', $gq);
} else {
setcookie("token", $ak['token'], time() + 3600 * 24 * 365, '/');
}
return $ak;
}
function user_login($app, $acw = 'username', $acx = 'password', $ch = 'user', $acy = 0)
{
$ab = ctx::data();
$ab = select_keys([$acw, $acx], $ab);
$acz = $ab[$acw];
$eq = $ab[$acx];
if (!$acz || !$eq) {
return NULL;
}
$ak = \db::row($ch, ["{$acw}" => $acz]);
if ($ak) {
if ($acy) {
$ux = $ak['salt'];
list($eq, $ux) = hashsalt($eq, $ux);
} else {
$eq = md5($eq);
}
if ($eq == $ak[$acx]) {
refresh_token($ch, $ak['id']);
return $ak;
}
}
return NULL;
}
function uc_auto_reg_user($ep, $adf)
{
$v = \uc::find_user(['username' => $ep]);
if ($v['code'] != 0) {
$v = uc::reg_user($ep, $adf);
} else {
$v = ['code' => 1, 'data' => []];
}
return $v;
}
function uc_login_data($bg)
{
$ay = uc::user_info($bg);
$ay = $ay['data'];
$fm = [];
$adg = uc::user_role($bg, 1);
$adh = [];
if ($adg['code'] == 0) {
$adh = $adg['data']['roles'];
if ($adh) {
foreach ($adh as $k => $fk) {
$fm[] = $fk['name'];
}
}
}
$ay['roles'] = $fm;
$adi = uc::user_domain($bg);
$ay['corps'] = array_values($adi['data']);
return [$bg, $ay, $adh];
}
function uc_user_login($app, $acw = 'username', $acx = 'password')
{
log_time("uc_user_login start");
$rs = $app->getContainer();
$z = $rs->request;
$ab = $z->getParams();
$ab = select_keys([$acw, $acx], $ab);
$acz = $ab[$acw];
$eq = $ab[$acx];
if (!$acz || !$eq) {
return NULL;
}
uc::init();
$v = uc::pwd_login($acz, $eq);
if ($v['code'] != 0) {
ret($v['code'], $v['message']);
}
$bg = $v['data']['access_token'];
return uc_login_data($bg);
}
function check_auth($app)
{
$z = req();
$adj = false;
$adk = cfg::get('public_paths');
$ft = $z->getUri()->getPath();
if ($ft == '/') {
$adj = true;
} else {
foreach ($adk as $bp) {
if (startWith($ft, $bp)) {
$adj = true;
}
}
}
info("check_auth: {$adj} {$ft}");
if (!$adj) {
if (is_weixin()) {
$gr = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $gr);
}
ret(1, 'auth error');
}
}
function extractUserData($adl)
{
return ['githubLogin' => $adl['login'], 'githubName' => $adl['name'], 'githubId' => $adl['id'], 'repos_url' => $adl['repos_url'], 'avatar_url' => $adl['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ak, $adm = false)
{
unset($ak['passwd']);
unset($ak['salt']);
if (!$adm) {
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
if (!isset($_SERVER['REQUEST_METHOD'])) {
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/pub/psysh';
}
$app = new \Slim\App();
ctx::app($app);
function tpl($bn, $adn = '.html')
{
$bn = $bn . $adn;
$ado = cfg::get('tpl_prefix');
$adp = "{$ado['pc']}/{$bn}";
$adq = "{$ado['mobile']}/{$bn}";
info("tpl: {$adp} | {$adq}");
return isMobile() ? $adq : $adp;
}
function req()
{
return ctx::req();
}
function get($br, $ps = '')
{
$z = req();
$u = $z->getParam($br, $ps);
if ($u == $ps) {
$adr = ctx::gets();
if (isset($adr[$br])) {
return $adr[$br];
}
}
return $u;
}
function post($br, $ps = '')
{
$z = req();
return $z->getParam($br, $ps);
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
$ft = $z->getUri()->getPath();
if (!startWith($ft, '/')) {
$ft = '/' . $ft;
}
return $ft;
}
function host_str($qt)
{
$ads = '';
if (isset($_SERVER['HTTP_HOST'])) {
$ads = $_SERVER['HTTP_HOST'];
}
return " [ {$ads} ] " . $qt;
}
function debug($qt)
{
$m = \cfg::get('app');
if (isset($m['log_level']) && $m['log_level'] == 100) {
if (ctx::logger()) {
$qt = format_log_str($qt, getCallerStr(3));
ctx::logger()->debug(host_str($qt));
}
}
}
function warn($qt)
{
if (ctx::logger()) {
$qt = format_log_str($qt, getCallerStr(3));
ctx::logger()->warn(host_str($qt));
}
}
function info($qt)
{
if (ctx::logger()) {
$qt = format_log_str($qt, getCallerStr(3));
ctx::logger()->info(host_str($qt));
}
}
function format_log_str($qt, $adt = '')
{
if (is_array($qt)) {
$qt = json_encode($qt);
}
return "{$qt} [ ::{$adt} ]";
}
function ck_owner($dk)
{
$bc = ctx::uid();
$jz = $dk['uid'];
debug("ck_owner: {$bc} {$jz}");
return $bc == $jz;
}
function _err($br)
{
return cfg::get($br, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bt = '', $vx = 0)
{
global $__log_time__, $__log_begin_time__;
list($tu, $tv) = explode(" ", microtime());
$adu = (double) $tu + (double) $tv;
if (!$__log_time__) {
$__log_begin_time__ = $adu;
$__log_time__ = $adu;
$bp = uripath();
debug("usetime: --- {$bp} ---");
return $adu;
}
if ($vx && $vx == 'begin') {
$adv = $__log_begin_time__;
} else {
$adv = $vx ? $vx : $__log_time__;
}
$vz = $adu - $adv;
$vz *= 1000;
debug("usetime: ---  {$vz} {$bt}  ---");
$__log_time__ = $adu;
return $adu;
}
use core\Service as ms;
$p = $app->getContainer();
$p['view'] = function ($rs) {
$bo = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$bo->addExtension(new \Slim\Views\TwigExtension($rs['router'], $rs['request']->getUri()));
return $bo;
};
$p['logger'] = function ($rs) {
if (is_docker_env()) {
$adw = '/ws/log/app.log';
} else {
$adx = cfg::get('logdir');
if ($adx) {
$adw = $adx . '/app.log';
} else {
$adw = __DIR__ . '/../app.log';
}
}
$ady = ['name' => '', 'path' => $adw];
$adz = new \Monolog\Logger($ady['name']);
$adz->pushProcessor(new \Monolog\Processor\UidProcessor());
$aef = \cfg::get('app');
$mn = isset($aef['log_level']) ? $aef['log_level'] : '';
if (!$mn) {
$mn = \Monolog\Logger::INFO;
}
$adz->pushHandler(new \Monolog\Handler\StreamHandler($ady['path'], $mn));
return $adz;
};
log_time();
unset($app->getContainer()['phpErrorHandler']);
unset($app->getContainer()['errorHandler']);
$p['notFoundHandler'] = function ($rs) {
if (!\ctx::isFoundRoute()) {
return function ($fq, $fr) use($rs) {
return $rs['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($fq, $fr) use($rs) {
return $rs['response'];
};
};
$p['ms'] = function ($rs) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($jv, $l, array $bd) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$aeg = ROOT_PATH . '/routes';
if (folder_exist($aeg)) {
$q = dir::scan($aeg, ['type' => 'file']);
foreach ($q as $r) {
if (basename($r) != 'routes.php' && !endWith($r, '.DS_Store')) {
require_once $r;
}
}
}
$aeh = cfg::get('opt_route_list');
if ($aeh) {
foreach ($aeh as $aj) {
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
$aei = cache('blockly_routes_key', function () {
return db::all('sys_blockly', ['AND' => ['code_type' => 'route']]);
}, 86400, 1);
foreach ($aei as $aej) {
$aek = get('nb');
if ($aek != 1) {
@eval($aej['phpcode']);
}
}
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $br, $dp = array())
{
$app->options("/hot/{$br}", function () {
ret([]);
});
$app->options("/hot/{$br}/{id}", function () {
ret([]);
});
$app->get("/hot/{$br}", function () use($dp, $br) {
$yz = $dp['objname'];
$ael = $br;
$cp = rest::getList($ael);
$acf = isset($dp['cols_map']) ? $dp['cols_map'] : [];
list($js, $acj, $ack, $cx) = getMetaData($yz, $acf);
$ack[0] = 10;
$v['data'] = ['meta' => $js, 'list' => $cp['data'], 'colHeaders' => $acj, 'colWidths' => $ack, 'cols' => $cx];
ret($v);
});
$app->get("/hot/{$br}/param", function () use($dp, $br) {
$yz = $dp['objname'];
$ael = $br;
$cp = rest::getList($ael);
list($acj, $ack, $cx) = getHotColMap1($ael);
$js = ['objname' => $yz];
$ack[0] = 10;
$v['data'] = ['meta' => $js, 'list' => [], 'colHeaders' => $acj, 'colWidths' => $ack, 'cols' => $cx];
ret($v);
});
$app->post("/hot/{$br}", function () use($dp, $br) {
$ael = $br;
$cp = rest::postData($ael);
ret($cp);
});
$app->put("/hot/{$br}/{id}", function ($z, $bl, $pq) use($dp, $br) {
$ael = $br;
$ab = ctx::data();
if (isset($ab['trans-word']) && isset($ab[$ab['trans-from']])) {
$aem = $ab['trans-from'];
$aen = $ab['trans-to'];
$u = util\Pinyin::get($ab[$aem]);
$ab[$aen] = $u;
}
ctx::data($ab);
$cp = rest::putData($ael, $pq['id']);
ret($cp);
});
}
function getHotColMap1($ael)
{
$aeo = $ael . '_param';
$aep = $ael . '_opt';
$aeq = $ael . '_opt_ext';
ctx::pagesize(50);
ctx::gets('pid', 6);
$cp = rest::getList($aeo);
$aer = getKeyValues($cp['data'], 'id');
$bd = indexArray($cp['data'], 'id');
$ew = db::all($aep, ['AND' => ['pid' => $aer]]);
$ew = indexArray($ew, 'id');
$aer = array_keys($ew);
$aes = db::all($aeq, ['AND' => ['pid' => $aer]]);
$aes = groupArray($aes, 'pid');
$acj = [];
$ack = [];
$cx = [];
foreach ($bd as $k => $aet) {
$acj[] = $aet['label'];
$ack[] = $aet['width'];
$cx[$aet['name']] = ['data' => $aet['name'], 'renderer' => 'html'];
}
foreach ($aes as $k => $ek) {
$aeu = '';
$acp = 0;
$aev = $ew[$k];
$aew = $aev['pid'];
$aet = $bd[$aew];
$aex = $aet['label'];
$aeu = $aet['name'];
if ($acp) {
}
if ($aeu) {
$cx[$aeu] = ['data' => $aeu, 'type' => 'autocomplete', 'strict' => false, 'source' => getKeyValues($ek, 'option')];
}
}
$cx = array_values($cx);
return [$acj, $ack, $cx];
$ab = ['rows' => $cp, 'pids' => $aer, 'props' => $aey, 'opts' => $ew, 'cols_map' => $acf];
$acf = [];
return $acf;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $br, $dp = array())
{
$ael = $br;
$aez = "{$br}_ext";
$app->get("/hot/{$br}", function () use($ael, $aez) {
$kl = get('oid');
$acp = get('pid');
$cj = "select * from `{$ael}` pp join `{$aez}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$kl} and pp.pid={$acp}";
$cp = db::query($cj);
$ab = groupArray($cp, 'name');
$acj = ['Id', 'Oid', 'RowNum'];
$ack = [5, 5, 5];
$cx = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ai = [];
foreach ($ab as $bu => $bv) {
$acj[] = $bv[0]['label'];
$ack[] = $bv[0]['col_width'];
$cx[] = ['data' => $bu, 'renderer' => 'html'];
$afg = [];
foreach ($bv as $k => $dk) {
$ai[$dk['_rownum']][$bu] = $dk['option'];
if ($bu == 'value') {
if (!isset($ai[$dk['_rownum']]['id'])) {
$ai[$dk['_rownum']]['id'] = $dk['id'];
$ai[$dk['_rownum']]['oid'] = $kl;
$ai[$dk['_rownum']]['_rownum'] = $dk['_rownum'];
}
}
}
}
$ai = array_values($ai);
$v['data'] = ['list' => $ai, 'colHeaders' => $acj, 'colWidths' => $ack, 'cols' => $cx];
ret($v);
});
$app->get("/hot/{$br}_addprop", function () use($ael, $aez) {
$kl = get('oid');
$acp = get('pid');
$afh = get('propname');
if ($afh != 'value' && !checkOptPropVal($kl, $acp, 'value', $ael, $aez)) {
addOptProp($kl, $acp, 'value', $ael, $aez);
}
if (!checkOptPropVal($kl, $acp, $afh, $ael, $aez)) {
addOptProp($kl, $acp, $afh, $ael, $aez);
}
ret([11]);
});
$app->options("/hot/{$br}", function () {
ret([]);
});
$app->options("/hot/{$br}/{id}", function () {
ret([]);
});
$app->post("/hot/{$br}", function () use($ael, $aez) {
$ab = ctx::data();
$acp = $ab['pid'];
$kl = $ab['oid'];
$afi = getArg($ab, '_rownum');
$afj = db::row($ael, ['AND' => ['oid' => $kl, 'pid' => $acp, 'name' => 'value']]);
if (!$afj) {
addOptProp($kl, $acp, 'value', $ael, $aez);
}
$afk = $afj['id'];
$afl = db::obj()->max($aez, '_rownum', ['pid' => $afk]);
$ab = ['oid' => $kl, 'pid' => $afk, '_rownum' => $afl + 1];
db::save($aez, $ab);
$v = ['oid' => $kl, '_rownum' => $afi, 'prop' => $afj, 'maxrow' => $afl];
ret($v);
});
$app->put("/hot/{$br}/{id}", function ($z, $bl, $pq) use($aez, $ael) {
$ab = ctx::data();
$acp = $ab['pid'];
$kl = $ab['oid'];
$afi = $ab['_rownum'];
$afi = getArg($ab, '_rownum');
$aw = $ab['token'];
$bc = $ab['uid'];
$dk = dissoc($ab, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($dk);
$k = key($dk);
$u = $dk[$k];
$afj = db::row($ael, ['AND' => ['pid' => $acp, 'oid' => $kl, 'name' => $k]]);
info("{$acp} {$kl} {$k}");
$afk = $afj['id'];
$afm = db::obj()->has($aez, ['AND' => ['pid' => $afk, '_rownum' => $afi]]);
if ($afm) {
debug("has cell ...");
$cj = "update {$aez} set `option`='{$u}' where _rownum={$afi} and pid={$afk}";
debug($cj);
db::exec($cj);
} else {
debug("has no cell ...");
$ab = ['oid' => $kl, 'pid' => $afk, '_rownum' => $afi, 'option' => $u];
db::save($aez, $ab);
}
$v = ['item' => $dk, 'oid' => $kl, '_rownum' => $afi, 'key' => $k, 'val' => $u, 'prop' => $afj, 'sql' => $cj];
ret($v);
});
}
function checkOptPropVal($kl, $acp, $br, $ael, $aez)
{
return db::obj()->has($ael, ['AND' => ['name' => $br, 'oid' => $kl, 'pid' => $acp]]);
}
function addOptProp($kl, $acp, $afh, $ael, $aez)
{
$br = Pinyin::get($afh);
$ab = ['oid' => $kl, 'pid' => $acp, 'label' => $afh, 'name' => $br];
$afj = db::save($ael, $ab);
$ab = ['_rownum' => 1, 'oid' => $kl, 'pid' => $afj['id']];
db::save($aez, $ab);
return $afj;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$afn = \cfg::load('mid');
if ($afn) {
foreach ($afn as $bu => $m) {
$afo = "\\{$bu}";
debug("load mid: {$afo}");
$app->add(new $afo());
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
