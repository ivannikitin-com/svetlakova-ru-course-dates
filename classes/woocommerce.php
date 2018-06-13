<?php
/**
 * Класс интеграции с WooCommerce
 * Обеспечивает основноые функции, обработку заказов по хукам WC
 */
class SVETLAKOVA_CD_WooCommerce extends SVETLAKOVA_CD__Base
{
	/**
	 * Ближайщая дата курсов
	 * @var int timestamp
	 */
	public $date;
	
	/**
	 * Конструктор 
	 * @param INWCOA_Plugin $plugin Экземпляр основного класса плагина
	 */
	public function __construct( $plugin )
	{
		// Вызов родительского конструктора
		parent::__construct( $plugin );
		
		// Рассчет ближайшей даты курса
		$this->date = $this->getDate();
		
		// Общие хуки
		add_action( 'add_meta_boxes', array( $this, 'addMetabox' ) );
		add_action( 'save_post', array( $this, 'saveMetabox' ) );
		
		
		// Подключаем хуки
		if ( $this->plugin->settings->isAttributeEnabled() )
			add_action( 'woocommerce_product_meta_end', array( $this, 'showDate') );
		
		// Подключаем хуки
		if ( $this->plugin->settings->isSchemaEnabled() )
		{
			add_filter( 'woocommerce_structured_data_product_offer', array( $this, 'setOffers'), 10, 2 );
			add_filter( 'woocommerce_structured_data_product', array( $this, 'generateEventMarkup'), 10, 2 );
			add_action( 'wp_footer', array( $this, 'outputStructuredData' ), 10 );
		}
	}
	
	/**
	 * Рассчет ближайшей даты курса
	 * @param int $currentDate Текущая дата, по умолчанию сегодня
	 * @return int timestamp
	 */
	public function getDate( $currentDate = 0 )
	{
		if ( empty( $currentDate ) )
			$currentDate = time();
		
		$dates = $this->plugin->settings->getDates();
		$year = date('Y', $currentDate);
		$month = date('n', $currentDate);
		$day = date('j', $currentDate);
		
		$newDay = 0;
		$iteration = 0;
		while ( $newDay == 0 )
		{
			$iteration++;
			
			// Ищем следующий день в этом месяце
			foreach ( $dates[ $month ] as $monthDay )
			{
				if ( $monthDay > $day )
				{
					$newDay = $monthDay;
					break;
				}				
			}
			
			// Найдено?
			if ( $newDay > 0 )
				break;
			
			// Смотрим следующий месяц
			$month++; 
			$day = 0;
			if ( $month == 13 )
			{
				$month = 1; 
				$year++;
			}
			
			// Предохранитель!
			if ( $iteration > 12 )
				break;
		}
		
		// Если не найдено, возвращаем пусто
		if ( $newDay == 0 )
			return apply_filters( SVETLAKOVA_CD . '_next_date', false );
		
		// Полученная дата
		$nextDate = mktime( 0, 0, 0, $month, $newDay, $year );
		return apply_filters( SVETLAKOVA_CD . '_next_date', $nextDate );
	}	
	
	/**
	 * Показывает ближайшую дату курса
	 */
	public function showDate()
	{
		global $product; 

		// Если не было генерации, ничего не делаем
		if ( ! $this->isProductSchemaEnabled( $product->get_id() ) )
			return;
		
		echo '<div class="svetlakova-ru-course-dates">Ближайшая дата: <span class="date">',
			date( 'd.m.Y', $this->date ),
			'</span></div>';
	}
	
	
	/**
	 * массив оферов
	 */
	private $offers = array();
	
	
	/**
	 * Запоминает оферы продуетов
	 */
	public function setOffers( $markup_offer, $product )
	{
		$this->offers = $markup_offer;
		return $markup_offer;
	}
	
	
	/**
	 * массив оферов
	 */
	private $event = array();	
	
	
	/**
	 * Генерирует разметку Event
	 */
	public function generateEventMarkup( $markup, $product ) 
	{
		// Если схема запрещена, ничего не делаем формируем пустой массив
		if ( ! $this->isProductSchemaEnabled( $product->get_id() ) )
		{
			$this->event = array();
			return;
		}	
		
		$this->event = array(
			'@context' 	=> 'http://schema.org',
			'@type'		=> 'Event',
			'name'		=> $product->get_name(),
			'startDate'	=> date( 'Y-m-dTH:i', $this->date ),
			'endDate'	=> date( 'Y-m-dTH:i', $this->date + $this->plugin->settings->getDuration() * DAY_IN_SECONDS ),
			'image'		=> wp_get_attachment_url( $product->get_image_id() ),
			'description'=> wpautop( do_shortcode( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ) ),
			'performer'	=> array(
				'@type' 	=> 'Organization',
				'name'		=> get_bloginfo( 'name' ),				
			),
			'location'	=> array(
				'@type' 	=> 'Place',
				'name'		=> get_bloginfo( 'name' ),
				'address'	=> array(
					'addressCountry'	=> get_option( 'woocommerce_default_country', '' ),
					'postalCode'		=> get_option( 'woocommerce_store_postcode', '' ),
					'addressLocality'	=> get_option( 'woocommerce_store_city', '' ),
					'streetAddress'		=> get_option( 'woocommerce_store_address', '' ) . ' ' . get_option( 'woocommerce_store_address_2', '' ),
				),
			),
		);
	}	
	
	/**
	 * Выводит на страницу продукта разметку schema.org
	 */
	public function outputStructuredData( )
	{
		// Если не было генерации, ничего не делаем
		if ( empty( $this->event ) )
			return;
		
		// Собираем все вместе
		$this->event['offers'] = $this->offers;		
		$data = wc_clean( apply_filters( SVETLAKOVA_CD . '_schema_markup', $this->event )  );
		if ( $data ) 
		{
			echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>';
		}
	}
	
	/* ----------------------------- Метабок товара ------------------------------- */
	
	/**
	 * Параметр сохранения мета-поля в товаре
	 */
	const META_PRODUCT_MARKUP_ENABLED = '_' . SVETLAKOVA_CD . '_enabled';
	
	/**
	 * Добавляет метабок к продукту
	 */
	public function addMetabox()
	{
		$screens = array( 'product' );
		add_meta_box( SVETLAKOVA_CD . 'metabox', 
			__( 'Разметка schema.org/Event', SVETLAKOVA_CD ), 
			array( $this, 'showMetabox' ), 
			$screens );
	}
	
	/**
	 * Показывает метабок к продукту
	 * @param $post	Текущий пост
	 * @param $meta	Массив с агрументами: metabox_id, title, callback 
	 */
	public function showMetabox( $post, $meta )
	{
		// Используем nonce для верификации
		wp_nonce_field( SVETLAKOVA_CD, SVETLAKOVA_CD . '_nonce' );
		
		$value = get_post_meta( $post->ID, self::META_PRODUCT_MARKUP_ENABLED, true );
		
		// Если поля нет, по умолчанию -- 1;
		if ( $value == '' )
			$value = '1';
		
		$checked = ( $value == '1' ) ? 'checked="checked"' : '';	

		// Поля формы для введения данных
		echo '<input type="checkbox" id="' . self::META_PRODUCT_MARKUP_ENABLED . '" name="' 
			. self::META_PRODUCT_MARKUP_ENABLED . '" value="1" ' . $checked . ' />';
		echo '<label for="' . self::META_PRODUCT_MARKUP_ENABLED . '">' . __('Включить разметку для этого продукта', SVETLAKOVA_CD ) . '</label> ';
	}
	
	/**
	 * Сохраняет мета-бокс
	 * @param $post_id	Текущий пост 
	 */
	public function saveMetabox( $post_id )
	{
		// Убедимся что поле установлено.
		if ( ! isset( $_POST[ SVETLAKOVA_CD . '_nonce' ] ) )
			return;

		// проверяем nonce нашей страницы, потому что save_post может быть вызван с другого места.
		if ( ! wp_verify_nonce( $_POST[ SVETLAKOVA_CD . '_nonce' ], SVETLAKOVA_CD ) )
			return;

		// если это автосохранение ничего не делаем
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
			return;

		// проверяем права юзера
		if( ! current_user_can( 'manage_woocommerce', $post_id ) )
			return;

		// Все ОК. Теперь, нужно найти и сохранить данные
		$value = ( isset( $_POST[ self::META_PRODUCT_MARKUP_ENABLED ] ) ) ? '1' : '0';

		// Обновляем данные в базе данных.
		update_post_meta( $post_id, self::META_PRODUCT_MARKUP_ENABLED, $value );		
	}
	
	/**
	 * Возвращает true, если генерация схемы не запрещена
	 * @param $post_id	Текущий пост 
	 */
	public function isProductSchemaEnabled( $product_id )
	{
		$value = get_post_meta( $product_id, self::META_PRODUCT_MARKUP_ENABLED, true );
		
		// Если явно запрещено, возвращает false
		if ( $value == '0' )
			return false;
		
		// Разрешаем во всех остальных случаях
		return true;
	}
	
	
}
