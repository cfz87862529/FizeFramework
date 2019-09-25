<?php


namespace fize\framework;

/**
 * 应用入口
 * @package fize\framework
 */
class App
{

    /**
     * @var array 环境变量
     */
    protected static $env;

    /**
     * @var string 当前分组
     */
    protected static $module;

    /**
     * @var string 当前控制器(全限定名)
     */
    protected static $controller;

    /**
     * @var string 当前操作
     */
    protected static $action;

    /**
     * 在此执行所有流程
     * @param array $env 环境配置
     */
    public function __construct(array $env = [])
    {
        $this->init($env);
        $this->config();
        $this->run();
    }

    /**
     * 初始化
     * @param array $env 参数
     */
    protected function init($env)
    {
        $default_env = [
            'root_path'      => null,  //根目录
            'app_dir'        => 'app',  //应用文件夹
            'config_dir'     => 'config',  //配置文件夹
            'runtime_dir'    => 'runtime',  //运行时文件夹
            'module'         => true,  //true表示开启分组并自动判断，false表示关闭分组，字符串表示指定分组
            'default_module' => 'index',  //开启分组时的默认分组
            'route_key'      => '_r',  //路由GET参数名
        ];
        $env = array_merge($default_env, $env);

        if (is_null($env['root_path'])) {
            $root_path = dirname(dirname(dirname(dirname(__FILE__))));  //使用composer放置在vendor文件夹中的相对位置
            $env['root_path'] = $root_path;
        }

        if ($env['module'] === false) {  //不使用分组
            self::$module = null;
        }
        if ($env['module'] === true) {  //自动判断分组
            $route = Request::get($env['route_key']);
            if($route) {
                $routes = explode('/', $route);
                self::$module = $routes[0];
            } else {
                self::$module = $env['default_module'];
            }
        }

        self::$env = $env;
    }

    /**
     * 载入配置
     */
    protected function config()
    {
        new Url(self::$env['route_key']);
        new Config(self::$env['module']);

        $cache_config = Config::get('cache');
        new Cache($cache_config['driver'], $cache_config['config']);

        $cookie_config = Config::get('cookie');
        new Cookie($cookie_config);

        $db_config = Config::get('db');
        new Db($db_config['type'], $db_config['mode'], $db_config['config']);

        $log_config = Config::get('log');
        new Log($log_config['driver'], $log_config['config']);

        $session_config = Config::get('session');
        new Session($session_config);

        $config_view = Config::get('view');
        new View($config_view['driver'], $config_view['config']);
    }

    /**
     * 执行逻辑
     */
    protected function run()
    {
        $config_controller = Config::get('controller');

        $route = Request::get(self::$env['route_key']);
        if($route) {
            $routes = explode('/', $route);
            if(self::$env['module'] === true) {  //自动判断
                array_shift($routes);
            }
            if(count($routes) == 0) {
                self::$controller = $config_controller['default_controller'];
                self::$action = $config_controller['default_action'];
            } elseif (count($routes) == 1) {
                self::$controller = ucfirst($routes[0]);
                self::$action = $config_controller['default_action'];
            } else {
                self::$action = array_pop($routes);
                $routes[count($routes) - 1] = ucfirst($routes[count($routes) - 1]);
                self::$controller = implode('/', $routes);
            }
        } else {
            self::$controller = $config_controller['default_controller'];
            self::$action = $config_controller['default_action'];
        }

        View::path(self::$controller . "/" . self::$action);

//        var_dump(self::$module);
//        var_dump(self::$controller);
//        var_dump(self::$action);
//        die();

        $class_path = '\\' . self::$env['app_dir'];
        if(self::$module) {
            $class_path .= '\\' . self::$module;
        }
        $class_path .= '\\controller\\' . self::$controller;

        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class_path . $config_controller['controller_postfix']);
        if(!class_exists($class)) {
            $class = str_replace('\\', DIRECTORY_SEPARATOR, $class_path);
            if(!class_exists($class)) {
                die('404');  //todo 出错的统一处理
            }
        }

        $action = self::$action;
        if(!method_exists($class, $action)){
            die('404');  //todo 出错的统一处理
        }

        $controller = new $class();
        $response = $controller->$action();
        //todo 善后工作
    }

    /**
     * 获取底层框架配置
     * @param string $key 如果指定该值，则返回该值指定的配置
     * @return mixed
     */
    public static function env($key = null)
    {
        if ($key) {
            return self::$env[$key];
        }
        return self::$env;
    }

    /**
     * 获取根目录路径
     * @return string
     */
    public static function rootPath()
    {
        return self::$env['root_path'];
    }

    /**
     * 获取应用目录路径
     * @return string
     */
    public static function appPath()
    {
        return self::$env['root_path'] . '/' . self::$env['app_dir'];
    }

    /**
     * 获取配置目录路径
     * @return string
     */
    public static function configPath()
    {
        return self::$env['root_path'] . '/' . self::$env['config_dir'];
    }

    /**
     * 获取运行目录路径
     * @return string
     */
    public static function runtimePath()
    {
        return self::$env['root_path'] . '/' . self::$env['runtime_dir'];
    }

    /**
     * 获取当前模块名，未启用模块时返回null
     * @return string
     */
    public static function module()
    {
        return self::$module;
    }

    /**
     * 获取当前控制器
     * @return string
     */
    public static function controller()
    {
        return self::$controller;
    }

    /**
     * 获取当前操作
     * @return string
     */
    public static function action()
    {
        return self::$action;
    }
}