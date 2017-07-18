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
public static function role()
{
return self::user()['role'];
}
public static function isRest()
{
return startWith(self::$_uripath, self::$_rest_prefix);
}
public static function isAdmin()
{
return self::user()['role'] == 'admin';
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
public static function getTokenUser($ap, $s)
{
$aq = $s->getParam('uid');
$ad = null;
$ar = $s->getParams();
$as = self::check_appid($ar);
if ($as && check_sign($ar, $as)) {
debug("appkey: {$as}");
$ad = ['id' => $aq, 'role' => 'admin'];
} else {
if (self::isStateless()) {
debug("isStateless");
$ad = ['id' => $aq, 'role' => 'user'];
} else {
$ao = self::getToken($s);
$at = self::getToken($s, 'access_token');
if (self::isEnableSso()) {
debug("getTokenUserBySso");
$ad = self::getTokenUserBySso($ao);
} else {
debug("get from db");
if ($ao) {
$ad = \db::row($ap, ['token' => $ao]);
} else {
if ($at) {
$ad = self::getAccessTokenUser($ap, $at);
}
}
}
}
}
return $ad;
}
public static function check_appid($ar)
{
$au = getArg($ar, 'appid');
if ($au) {
$m = cfg::get('support_service_list', 'service');
if (isset($m[$au])) {
debug("appid: {$au} ok");
return $m[$au];
}
}
debug("appid: {$au} not ok");
return '';
}
public static function getTokenUserBySso($ao)
{
$ad = ms('sso')->getuserinfo(['token' => $ao])->json();
return $ad;
}
public static function getAccessTokenUser($ap, $at)
{
$av = \db::row('oauth_access_tokens', ['access_token' => $at]);
if ($av) {
$aw = strtotime($av['expires']);
if ($aw - time() > 0) {
$ad = \db::row($ap, ['id' => $av['user_id']]);
}
}
return $ad;
}
public static function user_tbl($ax = null)
{
if ($ax) {
self::$_user_tbl = $ax;
}
return self::$_user_tbl;
}
public static function render($ay, $az, $bc, $t)
{
$bd = new \Slim\Views\Twig($az, ['cache' => false]);
self::$_foundRoute = true;
return $bd->render($ay, $bc, $t);
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
$be = str_replace(self::$_rest_prefix, '', self::uri());
$bf = explode('/', $be);
$bg = getArg($bf, 1, '');
$bh = getArg($bf, 2, '');
return [$bg, $bh];
}
public static function rest_select_add($bi = '')
{
if ($bi) {
self::$_rest_select_add = $bi;
}
return self::$_rest_select_add;
}
public static function rest_join_add($bi = '')
{
if ($bi) {
self::$_rest_join_add = $bi;
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
public static function gets($bj = '', $bk = '')
{
if (!$bj) {
return self::$_gets;
}
if (!$bk) {
return self::$_gets[$bj];
}
if ($bk == '_clear') {
$bk = '';
}
self::$_gets[$bj] = $bk;
return self::$_gets;
}
}
use Medoo\Medoo;
if (!function_exists('fixfn')) {
function fixfn($bl)
{
foreach ($bl as $bm) {
if (!function_exists($bm)) {
eval("function {$bm}(){}");
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
$bl = array('debug');
fixfn($bl);
class db
{
private static $_db_list;
private static $_db_default;
private static $_db;
private static $_dbc;
private static $_ins;
private static $tbl_desc = array();
public static function init($m, $bn = true)
{
self::init_db($m, $bn);
}
public static function init_db($m, $bn = true)
{
if (is_string($m)) {
$m = \cfg::get_db_cfg($m);
}
self::$_dbc = $m;
$bo = $m['database_name'];
self::$_db_list[$bo] = new Medoo($m);
if ($bn) {
self::use_db($bo);
}
}
public static function use_db($bo)
{
self::$_db = self::$_db_list[$bo];
}
public static function use_default_db()
{
self::$_db = self::$_db_default;
}
public static function obj()
{
if (!self::$_db) {
self::$_dbc = cfg::get_db_cfg();
self::$_db = self::$_db_default = new Medoo(self::$_dbc);
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
public static function desc_sql($bp)
{
if (self::db_type() == 'mysql') {
return "desc {$bp}";
} else {
if (self::db_type() == 'pgsql') {
return "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$bp}'";
} else {
return '';
}
}
}
public static function table_cols($bg)
{
$bq = self::$tbl_desc;
if (!isset($bq[$bg])) {
$br = self::desc_sql($bg);
if ($br) {
$bq[$bg] = self::query($br);
self::$tbl_desc = $bq;
debug("---------------- cache not found : {$bg}");
} else {
debug("empty desc_sql for: {$bg}");
}
}
if (!isset($bq[$bg])) {
return array();
} else {
return self::$tbl_desc[$bg];
}
}
public static function col_array($bg)
{
$bs = function ($bk) use($bg) {
return $bg . '.' . $bk;
};
return getKeyValues(self::table_cols($bg), 'Field', $bs);
}
public static function valid_table_col($bg, $bt)
{
$bu = self::table_cols($bg);
foreach ($bu as $bv) {
if ($bv['Field'] == $bt) {
$c = $bv['Type'];
return is_string_column($bv['Type']);
}
}
return false;
}
public static function tbl_data($bg, $t)
{
$bu = self::table_cols($bg);
$bw = [];
foreach ($bu as $bv) {
$bx = $bv['Field'];
if (isset($t[$bx])) {
$bw[$bx] = $t[$bx];
}
}
return $bw;
}
public static function test()
{
$br = "select * from tags limit 10";
$by = self::obj()->query($br)->fetchAll(PDO::FETCH_ASSOC);
var_dump($by);
}
public static function has_st($bg, $bz)
{
$cd = '_st';
return isset($bz[$cd]) || isset($bz[$bg . '.' . $cd]);
}
public static function getWhere($bg, $ce)
{
$cd = '_st';
if (!self::valid_table_col($bg, $cd)) {
return $ce;
}
$cd = $bg . '._st';
if (is_array($ce)) {
$cf = array_keys($ce);
$cg = preg_grep("/^AND\\s*#?\$/i", $cf);
$ch = preg_grep("/^OR\\s*#?\$/i", $cf);
$ci = array_diff_key($ce, array_flip(explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')));
if ($ci != array()) {
$bz = $ci;
if (!self::has_st($bg, $bz)) {
$ce[$cd] = 1;
$ce = ['AND' => $ce];
}
}
if (!empty($cg)) {
$l = array_values($cg);
$bz = $ce[$l[0]];
if (!self::has_st($bg, $bz)) {
$ce[$l[0]][$cd] = 1;
}
}
if (!empty($ch)) {
$l = array_values($ch);
$bz = $ce[$l[0]];
if (!self::has_st($bg, $bz)) {
$ce[$l[0]][$cd] = 1;
}
}
if (!isset($ce['AND']) && !self::has_st($bg, $bz)) {
$ce['AND'][$cd] = 1;
}
}
return $ce;
}
public static function all_sql($bg, $ce = '', $cj = '*', $ck = null)
{
if ($ck) {
$br = self::obj()->select_context($bg, $ck, $cj, $ce);
} else {
$br = self::obj()->select_context($bg, $cj, $ce);
}
return $br;
}
public static function all($bg, $ce = '', $cj = '*', $ck = null)
{
$ce = self::getWhere($bg, $ce);
if ($ck) {
$by = self::obj()->select($bg, $ck, $cj, $ce);
} else {
$by = self::obj()->select($bg, $cj, $ce);
}
return $by;
}
public static function count($bg, $ce = array('_st' => 1))
{
$ce = self::getWhere($bg, $ce);
return self::obj()->count($bg, $ce);
}
public static function row_sql($bg, $ce = '', $cj = '*', $ck = '')
{
return self::row($bg, $ce, $cj, $ck, true);
}
public static function row($bg, $ce = '', $cj = '*', $ck = '', $cl = null)
{
$ce = self::getWhere($bg, $ce);
if (!isset($ce['LIMIT'])) {
$ce['LIMIT'] = 1;
}
if ($ck) {
if ($cl) {
return self::obj()->select_context($bg, $ck, $cj, $ce);
}
$by = self::obj()->select($bg, $ck, $cj, $ce);
} else {
if ($cl) {
return self::obj()->select_context($bg, $cj, $ce);
}
$by = self::obj()->select($bg, $cj, $ce);
}
if ($by) {
return $by[0];
} else {
return null;
}
}
public static function one($bg, $ce = '', $cj = '*', $ck = '')
{
$cm = self::row($bg, $ce, $cj, $ck);
$cn = '';
if ($cm) {
$co = array_keys($cm);
$cn = $cm[$co[0]];
}
return $cn;
}
public static function save($bg, $t, $cp = 'id')
{
$cq = false;
if (!isset($t[$cp])) {
$cq = true;
} else {
if (!self::obj()->has($bg, [$cp => $t[$cp]])) {
$cq = true;
}
}
if ($cq) {
debug("insert {$bg} : " . json_encode($t));
self::obj()->insert($bg, $t);
$t['id'] = self::obj()->id();
} else {
debug("update {$bg} {$cp} {$t[$cp]}");
self::obj()->update($bg, $t, [$cp => $t[$cp]]);
}
return $t;
}
public static function update($bg, $t, $ce)
{
self::obj()->update($bg, $t, $ce);
}
public static function exec($br)
{
return self::obj()->query($br);
}
public static function query($br)
{
info($br);
return self::obj()->query($br)->fetchAll(PDO::FETCH_ASSOC);
}
public static function queryRow($br)
{
$by = self::query($br);
if ($by) {
return $by[0];
} else {
return null;
}
}
public static function queryOne($br)
{
$cm = self::queryRow($br);
return self::oneVal($cm);
}
public static function oneVal($cm)
{
$cn = '';
if ($cm) {
$co = array_keys($cm);
$cn = $cm[$co[0]];
}
return $cn;
}
public static function updateBatch($bg, $t)
{
$cr = $bg;
if (!is_array($t) || empty($cr)) {
return FALSE;
}
$br = "UPDATE `{$cr}` SET";
foreach ($t as $bh => $cm) {
foreach ($cm as $k => $cs) {
$ct[$k][] = "WHEN {$bh} THEN {$cs}";
}
}
foreach ($ct as $k => $cs) {
$br .= ' `' . trim($k, '`') . '`=CASE id ' . join(' ', $cs) . ' END,';
}
$br = trim($br, ',');
$br .= ' WHERE id IN(' . join(',', array_keys($t)) . ')';
return self::query($br);
}
}
class mdb
{
private static $_client;
private static $_db;
private static $_ins;
public static function obj($bo = 'myapp_dev')
{
if (!self::$_client) {
self::$_client = new \Sokil\Mongo\Client();
}
if (!self::$_db) {
self::$_db = self::$_client->{$bo};
}
return self::$_db;
}
public static function test()
{
$aq = 1;
$cu = self::obj()->blogs;
$cv = $cu->find()->findAll();
$t = object2array($cv);
$cw = 1;
foreach ($t as $bj => $cx) {
unset($cx['_id']);
unset($cx['tid']);
unset($cx['tags']);
if (isset($cx['_intm'])) {
$cx['_intm'] = date('Y-m-d H:i:s', $cx['_intm']['sec']);
}
if (isset($cx['_uptm'])) {
$cx['_uptm'] = date('Y-m-d H:i:s', $cx['_uptm']['sec']);
}
$cx['uid'] = $aq;
$bw = db::save('blogs', $cx);
$cw++;
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
self::$_client = $cy = new Predis\Client(cfg::get_redis_cfg());
}
return self::$_client;
}
}
class vld
{
public static function test($bg, $t)
{
}
public static function registration($t)
{
$bk = new Valitron\Validator($t);
$cz = ['required' => [['name'], ['gender'], ['birthdate'], ['blood'], ['nationality'], ['country'], ['mobile'], ['emergency_contact_person'], ['emergency_mobile'], ['cloth_size']], 'length' => [['name', 4]], 'mobile' => [['mobile']]];
$bk->rules($cz);
$bk->labels(['name' => '名称', 'gender' => '性别', 'birthdate' => '生日']);
if ($bk->validate()) {
return 0;
} else {
err($bk->errors());
}
}
}
}
namespace mid {
use FastRoute\Dispatcher;
use FastRoute\RouteParser\Std as StdParser;
class TwigMid
{
public function __invoke($de, $df, $dg)
{
log_time("Twig Begin");
$df = $dg($de, $df);
$dh = uripath($de);
debug(">>>>>> TwigMid START : {$dh}  <<<<<<");
if ($di = $this->getRoutePath($de)) {
$bd = \ctx::app()->getContainer()->view;
if (\ctx::isRetJson()) {
ret($bd->data);
}
$dj = rtrim($di, '/');
if ($dj == '/' || !$dj) {
$dj = 'index';
}
$bc = $dj;
$t = [];
if (isset($bd->data)) {
$t = $bd->data;
if (isset($bd->data['tpl'])) {
$bc = $bd->data['tpl'];
}
}
$t['uid'] = \ctx::uid();
$t['isLogin'] = \ctx::user() ? true : false;
$t['user'] = \ctx::user();
$t['uri'] = \ctx::uri();
$t['t'] = time();
$t['domain'] = \cfg::get('wechat_callback_domain');
$t['gdata'] = \ctx::global_view_data();
debug("<<<<<< TwigMid END : {$dh} >>>>>");
log_time("Twig End");
log_time("Twig Total", 'begin');
return $bd->render($df, tpl($bc), $t);
} else {
return $df;
}
}
public function getRoutePath($de)
{
$dk = \ctx::router()->dispatch($de);
if ($dk[0] === Dispatcher::FOUND) {
$ac = \ctx::router()->lookupRoute($dk[1]);
$dl = $ac->getPattern();
$dm = new StdParser();
$dn = $dm->parse($dl);
foreach ($dn as $do) {
foreach ($do as $dp) {
if (is_string($dp)) {
return $dp;
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
public function __invoke($de, $df, $dg)
{
log_time("AuthMid Begin");
$dh = uripath($de);
debug(">>>>>> AuthMid START : {$dh}  <<<<<<");
\ctx::init($de);
$this->check_auth($de, $df);
debug("<<<<<< AuthMid END : {$dh} >>>>>");
log_time("AuthMid END");
$df = $dg($de, $df);
return $df;
}
public function isAjax($be = '')
{
if ($be) {
if (startWith($be, '/rest')) {
$this->isAjax = true;
}
}
return $this->isAjax;
}
public function check_auth($s, $ay)
{
list($dq, $ad, $dr) = $this->auth_cfg();
$dh = uripath($s);
$this->isAjax($dh);
if ($dh == '/') {
return true;
}
$ds = $this->check_list($dq, $dh);
if ($ds) {
$this->check_admin();
}
$dt = $this->check_list($ad, $dh);
if ($dt) {
$this->check_user();
}
$du = $this->check_list($dr, $dh);
if (!$du) {
$this->check_user();
}
info("check_auth: {$dh} admin:[{$ds}] user:[{$dt}] pub:[{$du}]");
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
public function auth_error($dv = 1)
{
$dw = is_weixin();
$dx = isMobile();
$dy = \cfg::get('wechat_callback_domain');
info("auth_error: errorid: {$dv}, is_weixin: {$dw} , is_mobile: {$dx}");
$dz = $_SERVER['REQUEST_URI'];
if ($dw) {
header("Location: {$dy}/auth/wechat?_r={$dz}");
exit;
}
if ($dx) {
header("Location: {$dy}/auth/openwechat?_r={$dz}");
exit;
}
if ($this->isAjax()) {
ret($dv, 'auth error');
} else {
header('Location: /?_r=' . $dz);
exit;
}
}
public function auth_cfg()
{
$ef = \cfg::get('auth');
return [$ef['admin'], $ef['user'], $ef['public']];
}
public function check_list($ab, $dh)
{
foreach ($ab as $be) {
if (startWith($dh, $be)) {
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
public function __invoke($de, $df, $dg)
{
$this->init($de, $df, $dg);
log_time("{$this->classname} Begin");
$this->path_info = uripath($de);
debug(">>>>>> {$this->name}Mid START : {$this->path_info}  <<<<<<");
$this->handelReq($de, $df);
debug("<<<<<< {$this->name}Mid END : {$this->path_info} >>>>>");
log_time("{$this->classname} End");
$df = $dg($de, $df);
return $df;
}
public function handelReq($s, $ay)
{
$be = \cfg::get($this->classname, 'mid.yml');
if (is_array($be)) {
$this->handlePathArray($be, $s, $ay);
} else {
if (startWith($this->path_info, $be)) {
$this->handlePath($s, $ay);
}
}
}
public function handlePathArray($eg, $s, $ay)
{
foreach ($eg as $be => $eh) {
if (startWith($this->path_info, $be)) {
debug("{$this->path_info} match {$be} {$eh}");
$this->{$eh}($s, $ay);
break;
}
}
}

public function handlePath($s, $ay)
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
public function __invoke($de, $df, $dg)
{
log_time("RestMid Begin");
$this->path_info = uripath($de);
$this->rest_prefix = \cfg::get_rest_prefix();
debug(">>>>>> RestMid START : {$this->path_info}  <<<<<<");
if ($this->isRest($de)) {
info("====== RestMid Handle REST: {$this->path_info}  =======");
if ($this->isApiDoc($de)) {
$this->apiDoc($de);
} else {
$this->handelRest($de);
}
} else {
debug("====== RestMid PASS REST: {$this->path_info}  =======");
}
debug("<<<<<< RestMid END : {$this->path_info}  >>>>>");
log_time("RestMid Begin");
$df = $dg($de, $df);
return $df;
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
$be = str_replace($this->rest_prefix, '', $this->path_info);
$bf = explode('/', $be);
$bg = getArg($bf, 1, '');
$bh = getArg($bf, 2, '');
$eh = $s->getMethod();
info(" method: {$eh}, name: {$bg}, id: {$bh}");
$ei = "handle{$eh}";
$this->{$ei}($s, $bg, $bh);
}
public function handleGET($s, $bg, $bh)
{
if ($bh) {
rest::renderItem($bg, $bh);
} else {
rest::renderList($bg);
}
}
public function handlePOST($s, $bg, $bh)
{
self::beforeData($bg, 'post');
rest::renderPostData($bg);
}
public function handlePUT($s, $bg, $bh)
{
self::beforeData($bg, 'put');
rest::renderPutData($bg, $bh);
}
public function handleDELETE($s, $bg, $bh)
{
rest::delete($s, $bg, $bh);
}
public function handleOPTIONS($s, $bg, $bh)
{
sendJson([]);
}
public function beforeData($bg, $c)
{
$ej = \cfg::get('rest_maps', 'rest.yml');
$m = $ej[$bg][$c];
if ($m) {
$ek = $m['xmap'];
if ($ek) {
$t = \ctx::data();
foreach ($ek as $bj => $bk) {
unset($t[$bk]);
}
\ctx::data($t);
}
}
}
public function apiDoc($s)
{
$el = rd::genApi();
echo $el;
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
public static function whereStr($ce, $bg)
{
$bw = '';
foreach ($ce as $bj => $bk) {
if ($em = \db::valid_table_col($bg, $bj)) {
if ($em == 2) {
$bw .= " and t1.{$bj}='{$bk}'";
} else {
$bw .= " and t1.{$bj}={$bk}";
}
} else {
}
info("{$bg} {$bj} {$em}");
}
return $bw;
}
public static function getSqlFrom($bg, $en, $aq, $eo, $ep)
{
$eq = isset($_GET['tags']) ? 1 : 0;
$er = get('tags');
if ($er && is_array($er) && count($er) == 1 && !$er[0]) {
$er = '';
}
if ($eq) {
$es = '';
$et = 'not in';
if ($er) {
if (is_string($er)) {
$er = [$er];
}
$eu = implode("','", $er);
$es = "and `name` in ('{$eu}')";
$et = 'in';
$ev = " from {$bg} t1\n                               join tag_items t on t1.id=t.`oid`\n                               {$en}\n                               where t._st=1 and t1.uid={$aq} and t.tagid {$et}\n                               (select id from tags where type='{$bg}' {$es} )\n                               {$ep}";
} else {
$ev = " from {$bg} t1\n                              {$en}\n                              where t1.uid={$aq} and t1.id not in\n                              (select oid from tag_items where type='{$bg}')\n                              {$ep}";
}
} else {
$ew = "1=1";
if (!\ctx::isAdmin()) {
$ew = "t1.uid={$aq}";
if ($bg == \ctx::user_tbl()) {
$ew = "t1.id={$aq}";
}
}
$ev = "from {$bg} t1 {$en} where {$ew} {$eo} {$ep}";
}
return $ev;
}
public static function getSql($bg)
{
$aq = \ctx::uid();
$ex = get('sort', '_intm');
$ey = get('asc', -1);
if (!\db::valid_table_col($bg, $ex)) {
$ex = '_intm';
}
$ey = $ey > 0 ? 'asc' : 'desc';
$ep = " order by t1.{$ex} {$ey}";
$ez = gets();
$ez = un_select_keys(['sort', 'asc'], $ez);
$fg = get('_st', 1);
$ce = dissoc($ez, ['token', '_st']);
if ($fg != 'all') {
$ce['_st'] = $fg;
}
$eo = self::whereStr($ce, $bg);
$fh = get('search', '');
$fi = get('search-key', '');
if ($fh && $fi) {
$eo .= " and {$fi} like '%{$fh}%'";
}
$fj = \ctx::rest_select_add();
$en = \ctx::rest_join_add();
$ev = self::getSqlFrom($bg, $en, $aq, $eo, $ep);
$br = "select t1.* {$fj} {$ev}";
$fk = "select count(*) cnt {$ev}";
$y = \ctx::offset();
$x = \ctx::pagesize();
$br .= " limit {$y},{$x}";
return [$br, $fk];
}
public static function getList($bg)
{
$aq = \ctx::uid();
list($br, $fk) = self::getSql($bg);
$by = \db::query($br);
$ah = (int) \db::queryOne($fk);
$fl = \cfg::rest('rest_join_tags_list');
if ($fl && in_array($bg, $fl)) {
$fm = getKeyValues($by, 'id');
$er = tag::getTagsByOids($aq, $fm, $bg);
info('get tags ok');
foreach ($by as $bj => $cm) {
$fn = $er[$cm['id']];
$by[$bj]['tags'] = getKeyValues($fn, 'name');
}
info('set tags ok');
}
info('before ret');
return ['data' => $by, 'res-name' => $bg, 'count' => $ah];
}
public static function renderList($bg)
{
ret(self::getList($bg));
}
public static function getItem($bg, $bh)
{
$aq = \ctx::uid();
info("---GET---: {$bg}/{$bh}");
$fo = "{$bg}-{$bh}";
if ($bg == 'colls') {
$dp = \db::row($bg, ["{$bg}.id" => $bh], ["{$bg}.id", "{$bg}.title", "{$bg}.from_url", "{$bg}._intm", "{$bg}._uptm", "posts.content"], ['[>]posts' => ['uuid' => 'uuid']]);
} else {
if ($bg == 'feeds') {
$c = get('type');
$fp = get('rid');
$dp = \db::row($bg, ['AND' => ['uid' => $aq, 'rid' => $bh, 'type' => $c]]);
if (!$dp) {
$dp = ['rid' => $bh, 'type' => $c, 'excerpt' => '', 'title' => ''];
}
$fo = "{$fo}-{$c}-{$bh}";
} else {
$dp = \db::row($bg, ['id' => $bh]);
}
}
if (\ctx::rest_extra_data()) {
$dp = array_merge($dp, \ctx::rest_extra_data());
}
return ['data' => $dp, 'res-name' => $fo, 'count' => 1];
}
public static function renderItem($bg, $bh)
{
ret(self::getItem($bg, $bh));
}
public static function postData($bg)
{
$t = \db::tbl_data($bg, \ctx::data());
$aq = \ctx::uid();
$er = [];
if ($bg == 'tags') {
$er = tag::getTagByName($aq, $t['name'], $t['type']);
}
if ($er && $bg == 'tags') {
$t = $er[0];
} else {
info("---POST---: {$bg} " . json_encode($t));
unset($t['token']);
$t['_intm'] = date('Y-m-d H:i:s');
if (!isset($t['uid'])) {
$t['uid'] = $aq;
}
$t = \db::tbl_data($bg, $t);
\vld::test($bg, $t);
$t = \db::save($bg, $t);
}
return $t;
}
public static function renderPostData($bg)
{
$t = self::postData($bg);
ret($t);
}
public static function putData($bg, $bh)
{
if ($bh == 0 || $bh == '' || trim($bh) == '') {
info(" PUT ID IS EMPTY !!!");
ret();
}
$aq = \ctx::uid();
$t = \ctx::data();
unset($t['token']);
unset($t['uniqid']);
self::checkOwner($bg, $bh, $aq);
if (isset($t['inc'])) {
$fq = $t['inc'];
unset($t['inc']);
\db::exec("UPDATE {$bg} SET {$fq} = {$fq} + 1 WHERE id={$bh}");
}
if (isset($t['dec'])) {
$fq = $t['dec'];
unset($t['dec']);
\db::exec("UPDATE {$bg} SET {$fq} = {$fq} - 1 WHERE id={$bh}");
}
if (isset($t['tags'])) {
info("up tags");
tag::delTagByOid($aq, $bh, $bg);
$er = $t['tags'];
foreach ($er as $fr) {
$fs = tag::getTagByName($aq, $fr, $bg);
info($fs);
if ($fs) {
$ft = $fs[0]['id'];
tag::saveTagItems($aq, $ft, $bh, $bg);
}
}
}
info("---PUT---: {$bg}/{$bh} " . json_encode($t));
$t = \db::tbl_data($bg, \ctx::data());
$t['id'] = $bh;
\db::save($bg, $t);
return $t;
}
public static function renderPutData($bg, $bh)
{
$t = self::putData($bg, $bh);
ret($t);
}
public static function delete($s, $bg, $bh)
{
$aq = \ctx::uid();
self::checkOwner($bg, $bh, $aq);
\db::save($bg, ['_st' => 0, 'id' => $bh]);
ret([]);
}
public static function checkOwner($bg, $bh, $aq)
{
$ce = ['AND' => ['id' => $bh], 'LIMIT' => 1];
$by = \db::obj()->select($bg, '*', $ce);
if ($by) {
$dp = $by[0];
} else {
$dp = null;
}
if ($dp) {
if (array_key_exists('uid', $dp)) {
$fu = $dp['uid'];
if ($bg == \ctx::user_tbl()) {
$fu = $dp['id'];
}
if ($fu != $aq && (!\ctx::isAdmin() || !\ctx::isAdminRest())) {
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
public static function getTagByName($aq, $fr, $c)
{
$er = \db::all(self::$tbl_name, ['AND' => ['uid' => $aq, 'name' => $fr, 'type' => $c, '_st' => 1]]);
return $er;
}
public static function delTagByOid($aq, $fv, $fw)
{
info("del tag: {$aq}, {$fv}, {$fw}");
$bw = \db::update(self::$tbl_items_name, ['_st' => 0], ['AND' => ['uid' => $aq, 'oid' => $fv, 'type' => $fw]]);
info($bw);
}
public static function saveTagItems($aq, $fx, $fv, $fw)
{
\db::save('tag_items', ['tagid' => $fx, 'uid' => $aq, 'oid' => $fv, 'type' => $fw, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')]);
}
public static function getTagsByType($aq, $c)
{
$er = \db::all(self::$tbl_name, ['AND' => ['uid' => $aq, 'type' => $c, '_st' => 1]]);
return $er;
}
public static function getTagsByOid($aq, $fv, $c)
{
$br = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid={$fv} and t2.type='{$c}' and t2._st=1";
$by = \db::query($br);
return getKeyValues($by, 'name');
}
public static function getTagsByOids($aq, $fy, $c)
{
if (is_array($fy)) {
$fy = implode(',', $fy);
}
$br = "select * from tags t1 join tag_items t2 on t1.id = t2.tagid where t2.oid in ({$fy}) and t2.type='{$c}' and t2._st=1";
$by = \db::query($br);
$t = groupArray($by, 'oid');
return $t;
}
public static function countByTag($aq, $fr, $c)
{
$br = "select count(*) cnt, t1.id id from tags t1 join tag_items t2 on t1.id = t2.tagid where t1.name='{$fr}' and t1.type='{$c}' and t1.uid={$aq}";
$by = \db::query($br);
return [$by[0]['cnt'], $by[0]['id']];
}
public static function saveTag($aq, $fr, $c)
{
$t = ['uid' => $aq, 'name' => $fr, 'type' => $c, 'count' => 1, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
$t = \db::save('tags', $t);
return $t;
}
public static function countTags($aq, $fz, $bg)
{
foreach ($fz as $fr) {
list($gh, $bh) = self::countByTag($aq, $fr, $bg);
echo "{$fr} {$gh} {$bh} <br>";
\db::update('tags', ['count' => $gh], ['id' => $bh]);
}
}
public static function saveRepoTags($aq, $gi)
{
$bg = 'stars';
echo count($gi) . "<br>";
$fz = [];
foreach ($gi as $gj) {
$gk = $gj['repoId'];
$er = isset($gj['tags']) ? $gj['tags'] : [];
if ($er) {
foreach ($er as $fr) {
if (!in_array($fr, $fz)) {
$fz[] = $fr;
}
$er = self::getTagByName($aq, $fr, $bg);
if (!$er) {
$fs = self::saveTag($aq, $fr, $bg);
} else {
$fs = $er[0];
}
$fx = $fs['id'];
$gl = getStarByRepoId($aq, $gk);
if ($gl) {
$fv = $gl[0]['id'];
$gm = self::getTagsByOid($aq, $fv, $bg);
if ($fs && !in_array($fr, $gm)) {
self::saveTagItems($aq, $fx, $fv, $bg);
}
} else {
echo "-------- star for {$gk} not found <br>";
}
}
} else {
}
}
self::countTags($aq, $fz, $bg);
}
public static function getTagItem($gn, $aq, $go, $cp, $gp)
{
$br = "select * from {$go} where {$cp}={$gp} and uid={$aq}";
return $gn->query($br)->fetchAll();
}
public static function saveItemTags($gn, $aq, $bg, $gq, $cp = 'id')
{
echo count($gq) . "<br>";
$fz = [];
foreach ($gq as $gr) {
$gp = $gr[$cp];
$er = isset($gr['tags']) ? $gr['tags'] : [];
if ($er) {
foreach ($er as $fr) {
if (!in_array($fr, $fz)) {
$fz[] = $fr;
}
$er = getTagByName($gn, $aq, $fr, $bg);
if (!$er) {
$fs = saveTag($gn, $aq, $fr, $bg);
} else {
$fs = $er[0];
}
$fx = $fs['id'];
$gl = getTagItem($gn, $aq, $bg, $cp, $gp);
if ($gl) {
$fv = $gl[0]['id'];
$gm = getTagsByOid($gn, $aq, $fv, $bg);
if ($fs && !in_array($fr, $gm)) {
saveTagItems($gn, $aq, $fx, $fv, $bg);
}
} else {
echo "-------- star for {$gp} not found <br>";
}
}
} else {
}
}
countTags($gn, $aq, $fz, $bg);
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
public function __construct($gs = '')
{
if ($gs) {
$this->service = $gs;
$gt = self::$_services[$this->service];
$gu = $gt['url'];
debug("init client: {$gu}");
$this->client = new Client(['base_uri' => $gu, 'timeout' => 12.0]);
}
}
public static function add($gt = array())
{
if ($gt) {
$bg = $gt['name'];
if (!isset(self::$_services[$bg])) {
self::$_services[$bg] = $gt;
}
}
}
public static function init()
{
$gv = \cfg::get('service_list', 'service');
foreach ($gv as $m) {
self::add($m);
}
}
public function getRest($gs, $q = '/rest')
{
return $this->get($gs, $q . '/');
}
public function get($gs, $q = '')
{
if (isset(self::$_services[$gs])) {
if (!isset(self::$_ins[$gs])) {
self::$_ins[$gs] = new Service($gs);
}
}
if (isset(self::$_ins[$gs])) {
$gw = self::$_ins[$gs];
if ($q) {
$gw->setPrefix($q);
}
return $gw;
} else {
return null;
}
}
public function setPrefix($q)
{
$this->prefix = $q;
}
public function __call($gx, $gy)
{
$gt = self::$_services[$this->service];
$gu = $gt['url'];
$au = $gt['appid'];
$as = $gt['appkey'];
$t = $gy[0];
$t = array_merge($t, $_GET);
$t['appid'] = $au;
$t['date'] = date("Y-m-d H:i:s");
$t['sign'] = gen_sign($t, $as);
$eh = getArg($gy, 1, 'GET');
$gz = getArg($gy, 2, '');
$gx = $this->prefix . $gx . $gz;
debug("api_url: {$au} {$as} {$gu}");
debug("api_name: {$gx} {$eh}");
debug("data: " . json_encode($t));
try {
$this->resp = $this->client->request($eh, $gx, ['form_params' => $t]);
} catch (Exception $e) {
}
return $this;
}
public function json()
{
$bi = $this->body();
$t = json_decode($bi, true);
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
public function __get($hi)
{
$eh = 'get' . ucfirst($hi);
if (method_exists($this, $eh)) {
$hj = new ReflectionMethod($this, $eh);
if (!$hj->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $hi)) {
return $this->{$hi};
}
}
public function __set($hi, $l)
{
$eh = 'set' . ucfirst($hi);
if (method_exists($this, $eh)) {
$hj = new ReflectionMethod($this, $eh);
if (!$hj->isPublic()) {
throw new RuntimeException("The called method is not public ");
}
}
if (property_exists($this, $hi)) {
$this->{$hi} = $l;
}
}
}
}
namespace {
error_reporting(E_ALL);
function cache_shutdown_error()
{
$hk = error_get_last();
if ($hk && in_array($hk['type'], array(1, 4, 16, 64, 256, 4096, E_ALL))) {
echo '<font color=red>你的代码出错了：</font></br>';
echo '致命错误:' . $hk['message'] . '</br>';
echo '文件:' . $hk['file'] . '</br>';
echo '在第' . $hk['line'] . '行</br>';
}
}
register_shutdown_function("cache_shutdown_error");
function getCaller($hl = NULL)
{
$hm = debug_backtrace();
$hn = $hm[2];
if (isset($hl)) {
return $hn[$hl];
} else {
return $hn;
}
}
function getCallerStr($ho = 4)
{
$hm = debug_backtrace();
$hn = $hm[2];
$hp = $hm[1];
$hq = $hn['function'];
$hr = isset($hn['class']) ? $hn['class'] : '';
$hs = $hp['file'];
$ht = $hp['line'];
if ($ho == 4) {
$bi = "{$hr} {$hq} {$hs} {$ht}";
} elseif ($ho == 3) {
$bi = "{$hr} {$hq} {$ht}";
} else {
$bi = "{$hr} {$ht}";
}
return $bi;
}
function wlog($be, $hu, $hv)
{
if (is_dir($be)) {
$hw = date('Y-m-d', time());
$hv .= "\n";
file_put_contents($be . "/{$hu}-{$hw}.log", $hv, FILE_APPEND);
}
}
function folder_exist($hx)
{
$be = realpath($hx);
return ($be !== false and is_dir($be)) ? $be : false;
}
use Rosio\EncryptedCookie\CryptoSystem\AES_SHA;
function encrypt($t, $hy)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $t;
}
$hz = $m['symmetric_key'];
$ij = $m['hmac_key'];
$ik = new AES_SHA($hz, $ij);
return $ik->encrypt(serialize($t), $hy);
}
function decrypt($t)
{
$m = \cfg::get('encrypt');
if (!$m) {
return $t;
}
$hz = $m['symmetric_key'];
$ij = $m['hmac_key'];
$ik = new AES_SHA($hz, $ij);
return unserialize($ik->decrypt($t));
}
function encrypt_cookie($il)
{
return encrypt($il->getData(), $il->getExpiration());
}
define('UC_KEY', 'iHuiPaoiwoeurqoejjdfklasdjfqowiefiqwjflkjdfsdfa');
function _authcode($im, $in = 'DECODE', $k = '', $io = 0)
{
$ip = 4;
$k = md5($k ? $k : UC_KEY);
$iq = md5(substr($k, 0, 16));
$ir = md5(substr($k, 16, 16));
$is = $ip ? $in == 'DECODE' ? substr($im, 0, $ip) : substr(md5(microtime()), -$ip) : '';
$it = $iq . md5($iq . $is);
$iu = strlen($it);
$im = $in == 'DECODE' ? base64_decode(substr($im, $ip)) : sprintf('%010d', $io ? $io + time() : 0) . substr(md5($im . $ir), 0, 16) . $im;
$iv = strlen($im);
$iw = '';
$ix = range(0, 255);
$iy = array();
for ($cw = 0; $cw <= 255; $cw++) {
$iy[$cw] = ord($it[$cw % $iu]);
}
for ($iz = $cw = 0; $cw < 256; $cw++) {
$iz = ($iz + $ix[$cw] + $iy[$cw]) % 256;
$jk = $ix[$cw];
$ix[$cw] = $ix[$iz];
$ix[$iz] = $jk;
}
for ($jl = $iz = $cw = 0; $cw < $iv; $cw++) {
$jl = ($jl + 1) % 256;
$iz = ($iz + $ix[$jl]) % 256;
$jk = $ix[$jl];
$ix[$jl] = $ix[$iz];
$ix[$iz] = $jk;
$iw .= chr(ord($im[$cw]) ^ $ix[($ix[$jl] + $ix[$iz]) % 256]);
}
if ($in == 'DECODE') {
if ((substr($iw, 0, 10) == 0 || substr($iw, 0, 10) - time() > 0) && substr($iw, 10, 16) == substr(md5(substr($iw, 26) . $ir), 0, 16)) {
return substr($iw, 26);
} else {
return '';
}
} else {
return $is . str_replace('=', '', base64_encode($iw));
}
}

function object2array(&$jm)
{
$jm = json_decode(json_encode($jm), true);
return $jm;
}
function getKeyValues($t, $k, $bs = null)
{
if (!$bs) {
$bs = function ($bk) {
return $bk;
};
}
$jn = array();
if ($t && is_array($t)) {
foreach ($t as $dp) {
if (isset($dp[$k]) && $dp[$k]) {
$cs = $dp[$k];
if ($bs) {
$cs = $bs($cs);
}
$jn[] = $cs;
}
}
}
return array_unique($jn);
}
if (!function_exists('indexArray')) {
function indexArray($t, $k)
{
$jn = array();
if ($t && is_array($t)) {
foreach ($t as $dp) {
if (!isset($dp[$k]) || !$dp[$k] || !is_scalar($dp[$k])) {
continue;
}
$jn[$dp[$k]] = $dp;
}
}
return $jn;
}
}
if (!function_exists('groupArray')) {
function groupArray($jo, $k)
{
if (!is_array($jo) || !$jo) {
return array();
}
$t = array();
foreach ($jo as $dp) {
if (isset($dp[$k]) && $dp[$k]) {
$t[$dp[$k]][] = $dp;
}
}
return $t;
}
}
function select_keys($co, $t)
{
$bw = [];
foreach ($co as $k) {
if (isset($t[$k])) {
$bw[$k] = $t[$k];
} else {
$bw[$k] = '';
}
}
return $bw;
}
function un_select_keys($co, $t)
{
$bw = [];
foreach ($t as $bj => $dp) {
if (!in_array($bj, $co)) {
$bw[$bj] = $dp;
}
}
return $bw;
}
function copyKey($t, $jp, $jq)
{
foreach ($t as &$dp) {
$dp[$jq] = $dp[$jp];
}
return $t;
}
function addKey($t, $k, $cs)
{
foreach ($t as &$dp) {
$dp[$k] = $cs;
}
return $t;
}
function dissoc($jo, $co)
{
if (is_array($co)) {
foreach ($co as $k) {
unset($jo[$k]);
}
} else {
unset($jo[$co]);
}
return $jo;
}
function getArg($jr, $js, $jt = '')
{
if (isset($jr[$js])) {
return $jr[$js];
} else {
return $jt;
}
}
function permu($am, $ck = ',')
{
$ab = [];
if (is_string($am)) {
$ju = str_split($am);
} else {
$ju = $am;
}
sort($ju);
$jv = count($ju) - 1;
$jw = $jv;
$ah = 1;
$dp = implode($ck, $ju);
$ab[] = $dp;
while (true) {
$jx = $jw--;
if ($ju[$jw] < $ju[$jx]) {
$jy = $jv;
while ($ju[$jw] > $ju[$jy]) {
$jy--;
}

list($ju[$jw], $ju[$jy]) = array($ju[$jy], $ju[$jw]);

for ($cw = $jv; $cw > $jx; $cw--, $jx++) {
list($ju[$cw], $ju[$jx]) = array($ju[$jx], $ju[$cw]);
}
$dp = implode($ck, $ju);
$ab[] = $dp;
$jw = $jv;
$ah++;
}
if ($jw == 0) {
break;
}
}
return $ab;
}
function combin($jn, $jz, $kl = ',')
{
$iw = array();
if ($jz == 1) {
return $jn;
}
if ($jz == count($jn)) {
$iw[] = implode($kl, $jn);
return $iw;
}
$km = $jn[0];
unset($jn[0]);
$jn = array_values($jn);
$kn = combin($jn, $jz - 1, $kl);
foreach ($kn as $ko) {
$ko = $km . $kl . $ko;
$iw[] = $ko;
}
unset($kn);
$kp = combin($jn, $jz, $kl);
foreach ($kp as $ko) {
$iw[] = $ko;
}
unset($kp);
return $iw;
}
function getExcelCol($bt)
{
$jn = array(0 => 'Z', 1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G', 8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N', 15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U', 22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z');
if ($bt == 0) {
return '';
}
return getExcelCol((int) (($bt - 1) / 26)) . $jn[$bt % 26];
}
function getExcelPos($cm, $bt)
{
return getExcelCol($bt) . $cm;
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
function succ($jn = array(), $kq = 'succ', $kr = 1)
{
$t = $jn;
$ks = 0;
$kt = 1;
$ah = 0;
$bw = array($kq => $kr, 'errormsg' => '', 'errorfield' => '');
if (isset($jn['data'])) {
$t = $jn['data'];
}
if (isset($jn['total_page'])) {
$bw['total_page'] = $jn['total_page'];
}
if (isset($jn['cur_page'])) {
$bw['cur_page'] = $jn['cur_page'];
}
if (isset($jn['count'])) {
$bw['count'] = $jn['count'];
}
if (isset($jn['res-name'])) {
$bw['res-name'] = $jn['res-name'];
}
$bw['data'] = $t;
sendJSON($bw);
}
function fail($jn = array(), $kq = 'succ', $ku = 0)
{
$k = $hv = '';
if (count($jn) > 0) {
$co = array_keys($jn);
$k = $co[0];
$hv = $jn[$k][0];
}
$bw = array($kq => $ku, 'errormsg' => $hv, 'errorfield' => $k);
sendJSON($bw);
}
function code($jn = array(), $kv = 0)
{
if (is_string($kv)) {
}
if ($kv == 0) {
succ($jn, 'code', 0);
} else {
fail($jn, 'code', $kv);
}
}
function ret($jn = array(), $kv = 0, $fq = '')
{
$jl = $jn;
$kw = $kv;
if (is_numeric($jn) || is_string($jn)) {
$kw = $jn;
$jl = array();
if (is_array($kv)) {
$jl = $kv;
} else {
$kv = $kv === 0 ? '' : $kv;
$jl = array($fq => array($kv));
}
}
code($jl, $kw);
}
function err($kx)
{
code($kx, 1);
}
function downloadExcel($ky, $kz)
{
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header('Content-Disposition:inline;filename="' . $kz . '.xls"');
header("Content-Transfer-Encoding: binary");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
$ky->save('php://output');
}
function cacert_file()
{
return ROOT_PATH . "/fn/cacert.pem";
}
function curl($lm, $ln = 10, $lo = 30, $lp = '')
{
$lq = curl_init($lm);
curl_setopt($lq, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($lq, CURLOPT_CONNECTTIMEOUT, $ln);
curl_setopt($lq, CURLOPT_HEADER, 0);
curl_setopt($lq, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($lq, CURLOPT_TIMEOUT, $lo);
if (file_exists(cacert_file())) {
curl_setopt($lq, CURLOPT_CAINFO, cacert_file());
}
if ($lp) {
if (is_array($lp)) {
$lp = http_build_query($lp);
}
curl_setopt($lq, CURLOPT_POST, 1);
curl_setopt($lq, CURLOPT_POSTFIELDS, $lp);
}
$iw = curl_exec($lq);
if (curl_errno($lq)) {
return '';
}
curl_close($lq);
return $iw;
}
function curl_header($lm, $ln = 10, $lo = 30)
{
$lq = curl_init($lm);
curl_setopt($lq, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($lq, CURLOPT_CONNECTTIMEOUT, $ln);
curl_setopt($lq, CURLOPT_HEADER, 1);
curl_setopt($lq, CURLOPT_NOBODY, 1);
curl_setopt($lq, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.8) Gecko/20100202 Firefox/3.5.8 GTB7.0');
curl_setopt($lq, CURLOPT_TIMEOUT, $lo);
if (file_exists(cacert_file())) {
curl_setopt($lq, CURLOPT_CAINFO, cacert_file());
}
$iw = curl_exec($lq);
if (curl_errno($lq)) {
return '';
}
return $iw;
}

function startWith($bi, $ko)
{
return strpos($bi, $ko) === 0;
}
function endWith($lr, $ls)
{
$lt = strlen($ls);
if ($lt == 0) {
return true;
}
return substr($lr, -$lt) === $ls;
}
function is_string_column($c)
{
if (startWith(strtolower($c), 'char') || startWith(strtolower($c), 'varchar') || startWith(strtolower($c), 'datetime')) {
return 2;
} else {
return 1;
}
}
function getWhereStr($k, $t, $lu = false, $fq = '')
{
$jo = getKeyValues($t, $k);
if (!$jo) {
return '';
}
if ($lu) {
foreach ($jo as $bj => $bk) {
$jo[$bj] = "'{$bk}'";
}
}
$bi = implode(',', $jo);
if ($fq) {
$k = $fq;
}
return " {$k} in ({$bi})";
}
function get_top_domain($lm)
{
$dl = "/[\\w-]+\\.(com|net|org|gov|cc|biz|info|cn)(\\.(cn|hk))*/";
preg_match($dl, $lm, $lv);
if (count($lv) > 0) {
return $lv[0];
} else {
$lw = parse_url($lm);
$lx = $lw["host"];
if (!strcmp(long2ip(sprintf("%u", ip2long($lx))), $lx)) {
return $lx;
} else {
$jn = explode(".", $lx);
$ah = count($jn);
$ly = array("com", "net", "org", "3322");
if (in_array($jn[$ah - 2], $ly)) {
$dy = $jn[$ah - 3] . "." . $jn[$ah - 2] . "." . $jn[$ah - 1];
} else {
$dy = $jn[$ah - 2] . "." . $jn[$ah - 1];
}
return $dy;
}
}
}
function genID($hp)
{
list($lz, $mn) = explode(" ", microtime());
$mo = rand(0, 100);
return $hp . $mn . substr($lz, 2, 6);
}
function cguid($mp = false)
{
mt_srand((double) microtime() * 10000);
$mq = md5(uniqid(rand(), true));
return $mp ? strtoupper($mq) : $mq;
}
function guid()
{
if (function_exists('com_create_guid')) {
return com_create_guid();
} else {
$mr = cguid();
$ms = chr(45);
$mt = chr(123) . substr($mr, 0, 8) . $ms . substr($mr, 8, 4) . $ms . substr($mr, 12, 4) . $ms . substr($mr, 16, 4) . $ms . substr($mr, 20, 12) . chr(125);
return $mt;
}
}
function randstr($gh = 6)
{
return substr(md5(rand()), 0, $gh);
}
function hashsalt($mu, $mv = '')
{
$mv = $mv ? $mv : randstr(10);
$mw = md5(md5($mu) . $mv);
return [$mw, $mv];
}
function gen_letters($gh = 26)
{
$ko = '';
for ($cw = 65; $cw < 65 + $gh; $cw++) {
$ko .= strtolower(chr($cw));
}
return $ko;
}
function gen_sign($ar, $ao = null)
{
if ($ao == null) {
return false;
}
return strtoupper(md5(strtoupper(md5(assemble($ar))) . $ao));
}
function assemble($ar)
{
if (!is_array($ar)) {
return null;
}
ksort($ar, SORT_STRING);
$mx = '';
foreach ($ar as $k => $cs) {
$mx .= $k . (is_array($cs) ? assemble($cs) : $cs);
}
return $mx;
}
function check_sign($ar, $ao = null)
{
$mx = getArg($ar, 'sign');
$my = getArg($ar, 'date');
$mz = strtotime($my);
$no = time();
$np = $no - $mz;
debug("check_sign : {$no} - {$mz} = {$np}");
if (!$my || $no - $mz > 60) {
debug("check_sign fail : {$my} delta > 60");
return false;
}
unset($ar['sign']);
$nq = gen_sign($ar, $ao);
debug("{$mx} -- {$nq}");
return $mx == $nq;
}
function getIP()
{
if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
$nr = $_SERVER["HTTP_X_FORWARDED_FOR"];
} else {
if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
$nr = $_SERVER["HTTP_CLIENT_IP"];
} else {
if (!empty($_SERVER["REMOTE_ADDR"])) {
$nr = $_SERVER["REMOTE_ADDR"];
} else {
if (getenv("HTTP_X_FORWARDED_FOR")) {
$nr = getenv("HTTP_X_FORWARDED_FOR");
} else {
if (getenv("HTTP_CLIENT_IP")) {
$nr = getenv("HTTP_CLIENT_IP");
} else {
if (getenv("REMOTE_ADDR")) {
$nr = getenv("REMOTE_ADDR");
} else {
$nr = "Unknown";
}
}
}
}
}
}
return $nr;
}
function getRIP()
{
$nr = $_SERVER["REMOTE_ADDR"];
return $nr;
}
function env()
{
return getenv("DEV_MODE");
}
function vpath()
{
$be = getenv("VENDER_PATH");
if ($be) {
return $be;
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
$ns = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
if (preg_match("/(" . implode('|', $ns) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
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
function fixfn($bl)
{
foreach ($bl as $bm) {
if (!function_exists($bm)) {
eval("function {$bm}(){}");
}
}
}
function extractUserData($nt)
{
return ['githubLogin' => $nt['login'], 'githubName' => $nt['name'], 'githubId' => $nt['id'], 'repos_url' => $nt['repos_url'], 'avatar_url' => $nt['avatar_url'], '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
}
function output_user($ad, $nu = false)
{
unset($ad['passwd']);
unset($ad['salt']);
if (!$nu) {
unset($ad['token']);
}
unset($ad['access-token']);
ret($ad);
}
function cookie_test()
{
if (isset($_COOKIE['PHPSESSID'])) {
return true;
}
return false;
}
function auto_reg_user($nv = 'username', $nw = 'password', $bp = 'user', $nx = 0)
{
$ny = randstr(10);
$mu = randstr(6);
$t = ["{$nv}" => $ny, "{$nw}" => $mu, '_intm' => date('Y-m-d H:i:s'), '_uptm' => date('Y-m-d H:i:s')];
if ($nx) {
list($mu, $mv) = hashsalt($mu);
$t[$nw] = $mu;
$t['salt'] = $mv;
} else {
$t[$nw] = md5($mu);
}
return db::save($bp, $t);
}
function refresh_token($bp, $aq, $dy = '')
{
$nz = cguid();
$t = ['id' => $aq, 'token' => $nz];
info("refresh_token: {$nz}");
info($t);
$ad = db::save($bp, $t);
if ($dy) {
setcookie("token", $ad['token'], time() + 3600 * 24 * 365, '/', $dy);
} else {
setcookie("token", $ad['token'], time() + 3600 * 24 * 365, '/');
}
return $ad;
}
function user_login($app, $nv = 'username', $nw = 'password', $bp = 'user', $nx = 0)
{
$kw = $app->getContainer();
$s = $kw->request;
$t = $s->getParams();
$t = select_keys([$nv, $nw], $t);
$ny = $t[$nv];
$mu = $t[$nw];
if (!$ny || !$mu) {
return NULL;
}
$ad = \db::row($bp, ["{$nv}" => $ny]);
if ($ad) {
if ($nx) {
$mv = $ad['salt'];
list($mu, $mv) = hashsalt($mu, $mv);
} else {
$mu = md5($mu);
}
if ($mu == $ad[$nw]) {
return refresh_token($bp, $ad['id']);
}
}
return NULL;
}
function check_auth($app)
{
$s = req();
$op = false;
$oq = cfg::get('public_paths');
$dh = $s->getUri()->getPath();
if ($dh == '/') {
$op = true;
} else {
foreach ($oq as $be) {
if (startWith($dh, $be)) {
$op = true;
}
}
}
info("check_auth: {$op} {$dh}");
if (!$op) {
if (is_weixin()) {
$dz = $_SERVER['REQUEST_URI'];
header('Location: /api/auth/wechat?_r=' . $dz);
}
ret(1, 'auth error');
}
}
function ms($bg)
{
return \ctx::container()->ms->get($bg);
}
function rms($bg, $q = 'rest')
{
return \ctx::container()->ms->getRest($bg, $q);
}
use db\Rest as rest;
function getMetaData($or, $os = array())
{
ctx::pagesize(50);
$ot = rest::getList('sys_objects');
$ou = $ot['data'];
$ov = array_filter($ou, function ($bk) use($or) {
return $bk['name'] == $or;
});
$ov = array_shift($ov);
$ow = $ov['id'];
ctx::gets('oid', $ow);
$ox = rest::getList('sys_object_item');
$oy = $ox['data'];
$oz = ['Id'];
$pq = [0.1];
$cj = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true]];
foreach ($oy as $dp) {
$bg = $dp['name'];
$pr = $dp['colname'] ? $dp['colname'] : $bg;
$c = $dp['type'];
$jt = $dp['default'];
$ps = $dp['col_width'];
$pt = $dp['readonly'] ? ture : false;
$oz[] = $bg;
$pq[] = (double) $ps;
if (in_array($pr, array_keys($os))) {
$cj[] = $os[$pr];
} else {
$cj[] = ['data' => $pr, 'renderer' => 'html', 'readOnly' => $pt];
}
}
$oz[] = "InTm";
$oz[] = "St";
$pq[] = 60;
$pq[] = 10;
$cj[] = ['data' => "_intm", 'renderer' => "html", 'readOnly' => true];
$cj[] = ['data' => "_st", 'renderer' => "html"];
$pu = ['objname' => $or];
return [$pu, $oz, $pq, $cj];
}
$app = new \Slim\App();
ctx::app($app);
function tpl($bc, $pv = '.html')
{
$bc = $bc . $pv;
$pw = cfg::get('tpl_prefix');
$px = "{$pw['pc']}/{$bc}";
$py = "{$pw['mobile']}/{$bc}";
info("tpl: {$px} | {$py}");
return isMobile() ? $py : $px;
}
function req()
{
return ctx::req();
}
function get($bg, $jt = '')
{
$s = req();
$cs = $s->getParam($bg, $jt);
if ($cs == $jt) {
$pz = ctx::gets();
if (isset($pz[$bg])) {
return $pz[$bg];
}
}
return $cs;
}
function post($bg, $jt = '')
{
$s = req();
return $s->getParam($bg, $jt);
}
function gets()
{
$s = req();
$bw = $s->getQueryParams();
$bw = array_merge($bw, ctx::gets());
return $bw;
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
$dh = $s->getUri()->getPath();
if (!startWith($dh, '/')) {
$dh = '/' . $dh;
}
return $dh;
}
function host_str($ko)
{
$qr = '';
if (isset($_SERVER['HTTP_HOST'])) {
$qr = $_SERVER['HTTP_HOST'];
}
return " [ {$qr} ] " . $ko;
}
function debug($ko)
{
if (ctx::logger()) {
$ko = format_log_str($ko, getCallerStr(3));
ctx::logger()->debug(host_str($ko));
}
}
function warn($ko)
{
if (ctx::logger()) {
$ko = format_log_str($ko, getCallerStr(3));
ctx::logger()->warn(host_str($ko));
}
}
function info($ko)
{
if (ctx::logger()) {
$ko = format_log_str($ko, getCallerStr(3));
ctx::logger()->info(host_str($ko));
}
}
function format_log_str($ko, $qs = '')
{
if (is_array($ko)) {
$ko = json_encode($ko);
}
return "{$ko} [ ::{$qs} ]";
}
function ck_owner($dp)
{
$aq = ctx::uid();
$fu = $dp['uid'];
debug("ck_owner: {$aq} {$fu}");
return $aq == $fu;
}
function _err($bg)
{
return cfg::get($bg, 'error');
}
$__log_time__ = 0;
$__log_begin_time__ = 0;
function log_time($bi = '', $mz = 0)
{
global $__log_time__, $__log_begin_time__;
list($lz, $mn) = explode(" ", microtime());
$qt = (double) $lz + (double) $mn;
if (!$__log_time__) {
$__log_begin_time__ = $qt;
$__log_time__ = $qt;
$be = uripath();
debug("usetime: --- {$be} ---");
return $qt;
}
if ($mz && $mz == 'begin') {
$qu = $__log_begin_time__;
} else {
$qu = $mz ? $mz : $__log_time__;
}
$np = $qt - $qu;
$np *= 1000;
debug("usetime: ---  {$np} {$bi}  ---");
$__log_time__ = $qt;
return $qt;
}
use core\Service as ms;
$qv = $app->getContainer();
$qv['view'] = function ($kw) {
$bd = new \Slim\Views\Twig(ROOT_PATH . '/templates', ['cache' => false]);
$bd->addExtension(new \Slim\Views\TwigExtension($kw['router'], $kw['request']->getUri()));
return $bd;
};
$qv['logger'] = function ($kw) {
if (is_docker_env()) {
$qw = '/ws/log/app.log';
} else {
$qx = cfg::get('logdir');
if ($qx) {
$qw = $qx . '/app.log';
} else {
$qw = __DIR__ . '/../app.log';
}
}
$qy = ['name' => '', 'path' => $qw];
$qz = new \Monolog\Logger($qy['name']);
$qz->pushProcessor(new \Monolog\Processor\UidProcessor());
$rs = \cfg::get('app');
$ho = isset($rs['log_level']) ? $rs['log_level'] : '';
if (!$ho) {
$ho = \Monolog\Logger::INFO;
}
$qz->pushHandler(new \Monolog\Handler\StreamHandler($qy['path'], $ho));
$qz->pushHandler(new \Monolog\Handler\ChromePHPHandler());
return $qz;
};
log_time();
$qv['errorHandler'] = function ($kw) {
return function ($de, $df, $rt) use($kw) {
info($rt);
$ru = 'Something went wrong!';
return $kw['response']->withStatus(500)->withHeader('Content-Type', 'text/html')->write($ru);
};
};
$qv['notFoundHandler'] = function ($kw) {
if (!\ctx::isFoundRoute()) {
return function ($de, $df) use($kw) {
return $kw['response']->withStatus(404)->withHeader('Content-Type', 'text/html')->write('Page not found');
};
}
return function ($de, $df) use($kw) {
return $kw['response'];
};
};
$qv['ms'] = function ($kw) {
ms::init();
return new ms();
};
\Valitron\Validator::addRule('mobile', function ($fq, $l, array $ar) {
return preg_match("/^1[3|4|5|7|8]\\d{9}\$/", $l) ? TRUE : FALSE;
}, 'must be mobile number');
log_time("DEPS END");
log_time("ROUTES BEGIN");
use Lead\Dir\Dir as dir;
$rv = ROOT_PATH . '/routes';
if (folder_exist($rv)) {
$rw = dir::scan($rv, ['type' => 'file']);
foreach ($rw as $rx) {
if (basename($rx) != 'routes.php' && !endWith($rx, '.DS_Store')) {
require_once $rx;
}
}
}
$app->get('/route_list', function () {
debug("======= route list ========");
sendJSON(ctx::route_list());
});
log_time("ROUTES ENG");
}
namespace {
use db\Rest as rest;
function def_hot_rest($app, $bg, $ry = array())
{
$app->options("/pub/{$bg}", function () {
ret([]);
});
$app->options("/pub/{$bg}/{id}", function () {
ret([]);
});
$app->get("/pub/{$bg}", function () use($ry, $bg) {
$or = $ry['objname'];
$rz = $bg;
$by = rest::getList($rz);
list($pu, $oz, $pq, $cj) = getMetaData($or);
$pq[0] = 10;
$bw['data'] = ['meta' => $pu, 'list' => $by['data'], 'colHeaders' => $oz, 'colWidths' => $pq, 'cols' => $cj];
ret($bw);
});
$app->post("/pub/{$bg}", function () use($ry, $bg) {
$rz = $bg;
$by = rest::postData($rz);
ret($by);
});
$app->put("/pub/{$bg}/{id}", function ($s, $ay, $jr) use($ry, $bg) {
$rz = $bg;
$by = rest::putData($rz, $jr['id']);
ret($by);
});
}
function getHotColMap()
{
$rz = 'bom_part_params';
ctx::pagesize(50);
$by = rest::getList($rz);
$st = getKeyValues($by['data'], 'id');
$ar = indexArray($by['data'], 'id');
$su = db::all('bom_part_param_prop', ['AND' => ['part_param_id' => $st]]);
$su = groupArray($su, 'id');
$gt = db::all('bom_part_param_opt', ['AND' => ['param_id' => $st]]);
$gt = groupArray($gt, 'param_prop_id');
$os = [];
foreach ($gt as $k => $sv) {
$sw = '';
$sx = 0;
$sy = $su[$k];
foreach ($sy as $bj => $sz) {
if ($sz['name'] == 'value') {
$sx = $sz['part_param_id'];
}
}
$sw = $ar[$sx]['name'];
if ($sx) {
}
if ($sw) {
$os[$sw] = ['data' => $sw, 'type' => 'autocomplete', 'strict' => false, 'source' => getKeyValues($sv, 'option')];
}
}
$t = ['rows' => $by, 'pids' => $st, 'props' => $su, 'opts' => $gt, 'cols_map' => $os];
$os = [];
return $os;
}
}
namespace {
use db\Rest as rest;
use util\Pinyin;
function def_hot_opt_rest($app, $bg, $ry = array())
{
$rz = $bg;
$tu = "{$bg}_ext";
$app->get("/pub/{$bg}", function () use($rz, $tu) {
$fv = get('oid');
$sx = get('pid');
$br = "select * from `{$rz}` pp join `{$tu}` pv\n              on pp.id = pv.`pid`\n              where pp.oid={$fv} and pp.pid={$sx}";
$by = db::query($br);
$t = groupArray($by, 'name');
$oz = ['Id', 'Oid', 'RowNum'];
$pq = [5, 5, 5];
$cj = [['data' => 'id', 'renderer' => 'html', 'readOnly' => true], ['data' => 'oid', 'renderer' => 'html', 'readOnly' => true], ['data' => '_rownum', 'renderer' => 'html', 'readOnly' => true]];
$ab = [];
foreach ($t as $bj => $bk) {
$oz[] = $bk[0]['label'];
$pq[] = $bk[0]['col_width'];
$cj[] = ['data' => $bj, 'renderer' => 'html'];
$tv = [];
foreach ($bk as $k => $dp) {
$ab[$dp['_rownum']][$bj] = $dp['option'];
if ($bj == 'value') {
if (!isset($ab[$dp['_rownum']]['id'])) {
$ab[$dp['_rownum']]['id'] = $dp['id'];
$ab[$dp['_rownum']]['oid'] = $fv;
$ab[$dp['_rownum']]['_rownum'] = $dp['_rownum'];
}
}
}
}
$ab = array_values($ab);
$bw['data'] = ['list' => $ab, 'colHeaders' => $oz, 'colWidths' => $pq, 'cols' => $cj];
ret($bw);
});
$app->get("/pub/{$bg}_addprop", function () use($rz, $tu) {
$fv = get('oid');
$sx = get('pid');
$tw = get('propname');
if ($tw != 'value' && !checkOptPropVal($fv, $sx, 'value', $rz, $tu)) {
addOptProp($fv, $sx, 'value', $rz, $tu);
}
if (!checkOptPropVal($fv, $sx, $tw, $rz, $tu)) {
addOptProp($fv, $sx, $tw, $rz, $tu);
}
ret([11]);
});
$app->options("/pub/{$bg}", function () {
ret([]);
});
$app->options("/pub/{$bg}/{id}", function () {
ret([]);
});
$app->post("/pub/{$bg}", function () use($rz, $tu) {
$t = ctx::data();
$sx = $t['pid'];
$fv = $t['oid'];
$tx = $t['_rownum'];
$sz = db::row($rz, ['AND' => ['oid' => $fv, 'pid' => $sx, 'name' => 'value']]);
if (!$sz) {
addOptProp($fv, $sx, 'value', $rz, $tu);
}
$ty = $sz['id'];
$tz = db::obj()->max($tu, '_rownum', ['pid' => $ty]);
$t = ['oid' => $fv, 'pid' => $ty, '_rownum' => $tz + 1];
db::save($tu, $t);
$bw = ['oid' => $fv, '_rownum' => $tx, 'prop' => $sz, 'maxrow' => $tz];
ret($bw);
});
$app->put("/pub/{$bg}/{id}", function ($s, $ay, $jr) use($tu, $rz) {
$t = ctx::data();
$sx = $t['pid'];
$fv = $t['oid'];
$tx = $t['_rownum'];
$ao = $t['token'];
$aq = $t['uid'];
$dp = dissoc($t, ['oid', 'pid', '_rownum', '_uptm', 'uniqid', 'token', 'uid']);
debug($dp);
$k = key($dp);
$cs = $dp[$k];
$sz = db::row($rz, ['AND' => ['pid' => $sx, 'oid' => $fv, 'name' => $k]]);
info("{$sx} {$fv} {$k}");
$ty = $sz['id'];
$uv = db::obj()->has($tu, ['AND' => ['pid' => $ty, '_rownum' => $tx]]);
if ($uv) {
debug("has cell ...");
$br = "update {$tu} set `option`='{$cs}' where _rownum={$tx} and pid={$ty}";
debug($br);
db::exec($br);
} else {
debug("has no cell ...");
$t = ['oid' => $fv, 'pid' => $ty, '_rownum' => $tx, 'option' => $cs];
db::save($tu, $t);
}
$bw = ['item' => $dp, 'oid' => $fv, '_rownum' => $tx, 'key' => $k, 'val' => $cs, 'prop' => $sz, 'sql' => $br];
ret($bw);
});
}
function checkOptPropVal($fv, $sx, $bg, $rz, $tu)
{
return db::obj()->has($rz, ['AND' => ['name' => $bg, 'oid' => $fv, 'pid' => $sx]]);
}
function addOptProp($fv, $sx, $tw, $rz, $tu)
{
$bg = Pinyin::get($tw);
$t = ['oid' => $fv, 'pid' => $sx, 'label' => $tw, 'name' => $bg];
$sz = db::save($rz, $t);
$t = ['_rownum' => 1, 'oid' => $fv, 'pid' => $sz['id']];
db::save($tu, $t);
return $sz;
}
}
namespace {
log_time("MID BEGIN");
$app->add(new \mid\TwigMid());
$app->add(new \mid\RestMid());
$uw = \cfg::load('mid');
if ($uw) {
foreach ($uw as $bj => $m) {
$ux = "\\{$bj}";
debug("load mid: {$ux}");
$app->add(new $ux());
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
