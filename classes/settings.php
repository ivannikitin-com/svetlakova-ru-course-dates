<?php
/**
 * Класс интеграции с WooCommerce
 * Обеспечивает основноые функции, обработку заказов по хукам WC
 */
class SVETLAKOVA_CD_Settings extends SVETLAKOVA_CD__Base
{	
	/**
	 * Конструктор 
	 * @param SVETLAKOVA_CD_Plugin $plugin Экземпляр основного класса плагина
	 */
	public function __construct( $plugin )
	{
		// Вызов родительского конструктора
		parent::__construct( $plugin );
		
		// Хуки
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'addSettingsTab'), 50 );	// Добавляет новую страницу в настройки WC
		add_action( 'woocommerce_settings_tabs_'. SVETLAKOVA_CD , array( $this, 'showSettings') );		// Показывает настройки на новой панели
		add_action( 'woocommerce_update_options_'. SVETLAKOVA_CD , array( $this, 'updateSettings') );	// Обновляет настройки на новой панели
	}
	
	/* -------------------------- Методы запроса настроек -------------------------- */
	/**
	 * Даты курсов по умолчанию
	 */
	const DATES_DEFAULT = '05.01, 15.01, 01.02, 15.02, 01.03, 15.03, 01.04, 15.04, 05.05, 15.05, 01.06, 15.06, 01.07, 15.07, 01.08, 15.08, 01.09, 15.09, 01.10, 15.10, 01.11, 15.11, 01.12, 15.12';	
	
	/**
	 * Вовзращает массив с датами курсов
	 * @retun mixed array[ month ] => array( dates )
	 */
	public function getDates()
	{
		// Пустой массив в 12 месяцами
		$dates = array();
		for ( $i = 1; $i <= 12; $i++ )
			$dates[ $i ] = array();
		
		// Текущие параметры
		$datesStr = get_option( SVETLAKOVA_CD . '_dates' );
		if ( empty( $datesStr ) )
			$datesStr = self::DATES_DEFAULT;
		
		// Делим на отдельные записи
		$dateRecs = explode( ',', $datesStr );
		
		// Заполняем массив
		foreach ( $dateRecs as $rec )
		{
			list( $date, $month ) = explode( '.', $rec );
			$date = (int) $date;
			$month = (int) $month;
			
			if ( empty( $date) || empty( $month ) )
				continue;
			
			$dates[ $month ][] = $date;
		}
		
		// Сортируем даты
		foreach ( array_keys( $dates ) as $month )
		{
			sort( $dates[ $month ] );
		}
		
		return apply_filters( SVETLAKOVA_CD . '_dates', $dates );
	}
	
	/**
	 * Вовзращает true если нужно добавить атрибут с датой кураса в товар WC 
	 * @retun bool
	 */
	public function isAttributeEnabled()
	{
		$value = (bool) get_option( SVETLAKOVA_CD . '_enable_attr' );
		return (bool) apply_filters( SVETLAKOVA_CD . '_enable_attr', $value );
 	}
	
	/**
	 * Вовзращает true если нужно добавить разметку schema.org/Event 
	 * @retun bool
	 */
	public function isSchemaEnabled()
	{
		$value = (bool) get_option( SVETLAKOVA_CD . '_enable_schema' );
		return (bool) apply_filters( SVETLAKOVA_CD . '_enable_schema', $value );
 	}	

	/**
	 * Вовзращает продолжительность курса в днях 
	 * @retun int
	 */
	public function getDuration()
	{
		$value = (int) get_option( SVETLAKOVA_CD . '_duration' );
		return (int) apply_filters( SVETLAKOVA_CD . '_duration', $value );
 	}	
	
	
	/* -------------------------- Отображение настроек -------------------------- */
	/**
	 * Добавляет новую панель в настройки WooCommerce
	 * @param mixed $tabs Массив панедей WC
	 */
	public function addSettingsTab( $tabs )
	{
		$tabs[ SVETLAKOVA_CD ] = __( 'Даты курсов', SVETLAKOVA_CD );
		return $tabs;		
	}
	
	/**
	 * Показывает настройки плагина
	 */
	public function showSettings()
	{
		woocommerce_admin_fields( $this->getSettings() );	
	}
	
	/**
	 * Обновляет настройки плагина
	 */
	public function updateSettings()
	{
		woocommerce_update_options( $this->getSettings() );
	}
	
	/**
	 * Возвращает массив параметров для страницы настроек WooCommerce
	 * @return mixed 
	 */
	public function getSettings()
	{
	   return array(
			'section_title' => array(
				'name'     => __( 'Даты курсов', SVETLAKOVA_CD ),
				'type'     => 'title',
				'desc'     => '',
				'id'       => SVETLAKOVA_CD . '_section_title'
			),
			'dates' => array(
				'name' => __( 'Даты по месяцам', SVETLAKOVA_CD ),
				'type' => 'textarea',
				'default' => self::DATES_DEFAULT,
				'desc' => __( 'Даты в формате dd.mm разделяются запятой', SVETLAKOVA_CD ),
				'css'  => 'height:6em',
				'id'   => SVETLAKOVA_CD . '_dates',
			),
			'duration' => array(
				'name' => __( 'Продолжительность курса', SVETLAKOVA_CD ),
				'type' => 'text',
				'default' => '5',
				'desc' => __( 'Продолжительность курса в днях', SVETLAKOVA_CD ),
				'css'  => 'width:5em',
				'id'   => SVETLAKOVA_CD . '_duration',
			),
		   'attribute' => array(
				'name' => __( 'Вывод даты в свойствах товара', SVETLAKOVA_CD ),
				'type' => 'checkbox',
				'default' => true,
				'desc' => __( 'Установите для вывода ближайшей даты в свойствах товара', SVETLAKOVA_CD ),
				'id'   => SVETLAKOVA_CD . '_enable_attr'
			),
			'schema' => array(
				'name' => __( 'Разметка schema.org/Event', SVETLAKOVA_CD ),
				'type' => 'checkbox',
				'default' => true,
				'desc' => __( 'Установите для включения разметки schema.org/Event', SVETLAKOVA_CD ),
				'id'   => SVETLAKOVA_CD . '_enable_schema'
			),		   
			'section_end' => array(
				 'type' => 'sectionend',
				 'id' => SVETLAKOVA_CD . '_section_end'
			)
		);
	}	
	
}