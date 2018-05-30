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
	public function showDate(  )
	{
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
}
