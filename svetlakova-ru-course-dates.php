<?php
/**
 * Plugin Name: svetlakova.ru Course Dates
 * Plugin URI: https://github.com/ivannikitin-com/svetlakova-ru-course-dates
 * Description: Плагин поставляет даты начала курса во все продукты WooCommerce и формирует разметку schema.org/event
 * Version: 1.0
 * Author: Иван Никитин и партнеры
 * Author URI: https://ivannikitin.com
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'SVETLAKOVA_CD', 'svetlakova-ru-course-dates' );	// Константа текстового домена и других параметов плагина 

// Основной класс плагина
class SVETLAKOVA_CD_Plugin
{
	/**
	 * Версия
	 */
	public $version;
	
	/**
	 * Путь к папке плагина
	 */
	public $path;
	
	/**
	 * URL к папке плагина
	 */
	public $url;
	
	/**
	 * Конструктор плагина
	 */
	public function __construct()
	{
		// Инициализация свойств
		$this->version = '1.0';
		$this->path = plugin_dir_path( __FILE__ );
		$this->url = plugin_dir_url( __FILE__ );
		
		// Автозагрузка классов
		spl_autoload_register( array( $this, 'autoload' ) );

		// Активация плагина
		//register_activation_hook( __FILE__, 'SVETLAKOVA_CD_User::registerRoles' );		
		
		// Хуки
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		add_action( 'init', array( $this, 'init' ) );
	}
	
    /**
     * Автозагрузка лассов по требованию
     *
     * @param string $class Требуемый класс
     */
    function autoload( $class ) 
	{
        $classPrefix = 'SVETLAKOVA_CD_';
	
		// Если это не наш класс, ничего не делаем...
		if ( strpos( $class, $classPrefix ) === false ) 
			return;
		
		$fileName   = $this->path . 'classes/' . strtolower( str_replace( $classPrefix, '', $class ) ) . '.php';
		if ( file_exists( $fileName ) ) 
		{
			require_once $fileName;
		}
    }
	
	/**
	 * Плагины загружены
	 */
	public function plugins_loaded()
	{
		// Локализация
		load_plugin_textdomain( SVETLAKOVA_CD, false, basename( dirname( __FILE__ ) ) . '/lang' );
	}

	/**
	 * Объект параметров и настроек
	 * @var SVETLAKOVA_CD_Settings
	 */
	public $settings;
	
	/**
	 * Объект интеграции с WooCOmmerce
	 * @var SVETLAKOVA_CD_WooCommerce
	 */
	public $wc;	
	
	/**
	 * Инициализация компонентов плагина
	 */
	public function init()
	{
		// Проверка наличия WC		
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
		{
			add_action( 'admin_notices', array( $this, 'showNoticeNoWC' ) );
			return;
		}		
		
		// Инициализация объектов
		$this->settings = new SVETLAKOVA_CD_Settings( $this );
		$this->wc = new SVETLAKOVA_CD_WooCommerce( $this );
		
	}
	
	/**
	 * Предупреждение об отсутствии WooCommerce
	 */
	public function showNoticeNoWC()
	{ ?>
    <div class="notice notice-warning no-woocommerce">
        <p><?php _e( 'Для работы плагина "svetlakova.ru Course Dates" требуется установить и активировать плагин WooCommerce.', SVETLAKOVA_CD ); ?></p>
        <p><?php _e( 'В настоящий момент все функции плагина деактивированы.', SVETLAKOVA_CD ); ?></p>
    </div>		
<?php }
	
}

// Запуск плагина
new SVETLAKOVA_CD_Plugin();
