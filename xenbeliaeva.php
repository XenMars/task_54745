<?php

// Регистрируем два кастомных типа записей "Товары" и "Наборы"
add_action( 'init', 'customPostTypes' );

function customPostTypes(){
    // Регистрируем тип записи "Товары"
    register_post_type( 'products', [
        'label'  => null,
        'labels' => [
            'name'               => __('Товары'),
            'singular_name'      => __('Товар'),
        ],
        'menu_icon'           => 'dashicons-category',
        'description'         => '',
        'public'              => true, 
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'show_in_rest'        => true,
        'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields'], 
        'taxonomies'          => ['brands'],
    ] );
    // Регистрируем тип записи "Наборы"
    register_post_type( 'sets', [
        'label'  => null,
        'labels' => [
            'name'               => __('Наборы'),
            'singular_name'      => __('Набор'),
        ],
        'menu_icon'           => 'dashicons-tagcloud',
        'description'         => '',
        'public'              => true,
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'show_in_rest'        => true,
        'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ],
        'taxonomies'          => ['brands'],
    ] );
}

// Регистрируем кастомную таксономию "Бренды"
add_action('init', 'customTaxonomy');

function customTaxonomy() {
    register_taxonomy('brands', ['products', 'sets'], [
        'label' => __('Бренды'),
        'rewrite' => ['slug' => 'brands'],
        'public'  =>   true,
        'hierarchical' => true,
        'show_in_rest' => true,
    ]);

    // Связываем таксономию "Бренды" с типами записей "Товары" и "Наборы"
    register_taxonomy_for_object_type('brands', 'products');
    register_taxonomy_for_object_type('brands', 'sets');
}

// Добавляем пользовательское поле метаданных "Цена" на страницу редактирования пост
add_action( 'init', 'registerMetaPrice');

function registerMetaPrice(){

    register_meta('post', 'metaprice',
        array(
            'type'           => 'number',
            'single'         => true,
            'show_in_rest'   => true,
        )
    );

}

// Добавляем колонку "Бренд" в таблицу постов в админке
add_filter('manage_posts_columns', 'add_brand_column');
// Отображаем значение "Бренд" в соответствующей колонке таблицы постов в админке
add_action('manage_posts_custom_column', 'display_brand_column', 10, 2);

// Добавляем колонку "Бренд" в таблицу постов в админке
function add_brand_column($columns) {
    $columns['brand'] = 'Бренд';
    return $columns;
}

// Отображаем значение "Бренд" в соответствующей колонке таблицы постов в админке
function display_brand_column($column_name, $post_id) {
    if ($column_name == 'brand') {
        $brands = wp_get_post_terms($post_id, 'brands');
        $brand_names = array();
        foreach ($brands as $brand) {
            $brand_names[] = $brand->name;
        }
        echo implode(', ', $brand_names); //echo implode - это PHP функция, которая используется для объединения массива строк в одну строку и вывода ее на экран.
    }
}

// Регистрируем функцию registerPriceBlock() в WordPress init action hook
add_action('init', 'registerPriceBlock');
// Определяем функция которая регистрирует блок Gutenberg с ценой
function registerPriceBlock()
{
    // Проверяем, является ли пользователь администратором
    if (is_admin()) {
        global $pagenow;
        $current_type = '';
        // Если мы на странице создания нового поста, то определяем его тип
        if ( 'post-new.php' === $pagenow ) {
            if ( isset( $_REQUEST['post_type'] ) && post_type_exists( $_REQUEST['post_type'] ) ) {
                $current_type = $_REQUEST['post_type'];
            };
        // Если мы на странице редактирования существующего поста, то определяем его тип
        } elseif ( 'post.php' === $pagenow ) {
            if ( isset( $_GET['post'] ) && isset( $_POST['post_ID'] ) && (int) $_GET['post'] !== (int) $_POST['post_ID'] ) {
                // Ничего не делаем
            } elseif ( isset( $_GET['post'] ) ) {
                $post_id = (int) $_GET['post'];
            } elseif ( isset( $_POST['post_ID'] ) ) {
                $post_id = (int) $_POST['post_ID'];
            }
            if ( $post_id ) {
                $post = get_post( $post_id );
                $current_type = $post->post_type;
            }
        }
        // Если текущий тип не является products или sets, то завершаем функцию
        if (!in_array($current_type,['products', 'sets'])) {
            return;
        }
    }
    // Регистрируем блок в WordPress
    register_block_type( __DIR__ . '/blockprice/build/block.json');
}

// Добавляем хук, который будет вызывать функцию settingSet после добавления новой записи в WordPress
add_action( 'wp_after_insert_post', 'settingSet', 10, 3 );

// Определяем функцию, которая будет выполняться после добавления новой записи
function settingSet($post_id, $post, $post_before) {
    // Проверяем, что тип записи является "products"
    if( 'products' !== $post->post_type) {
        return; // Если нет, то функция завершается
    }

    // Проверяем, что статус записи "опубликован"
    if ($post->post_status !== 'publish') {
        return; // Если нет, то функция завершается
    }

    // Получаем список терминов записи с таксономией "brands"
    $brand_terms = wp_get_post_terms($post_id, 'brands');
    if (empty($brand_terms)) {
        return; // Если список терминов пуст, то функция завершается
    }

    // Получаем идентификатор термина "brands" для данной записи
    $brand_id = $brand_terms[0]->term_id;


    // Получаем список записей с типами "products" и "sets", относящихся к данному термину "brands"
    $brand_posts = get_posts(array(
        'post_type' => array('products', 'sets'),
        'tax_query' => array(
            array(
                'taxonomy' => 'brands',
                'terms' => $brand_id,
            )
        )
    ));

    // Если количество записей меньше или равно 1, то функция завершается
    if (count($brand_posts) <= 1) {
        return;
    }


    // Получаем список терминов записи с таксономией "brands"
    $product_brands = wp_get_post_terms($post_id, 'brands');
    // Получаем название первого термина "brands", если он есть
    $brand_name = $product_brands[0]->name ?: null;
    // Вычисляем цену для набора товаров, относящихся к данному термину "brands"
    $price = calculateDiscount($brand_id);
    // Получаем список записей типа "sets", относящихся к данному термину "brands"
    $setbrands = postsInBrans('sets', $brand_id);

    // Если запись типа "sets" уже существует, то обновляем мета-поле "metaprice" у этой записи
    if($setbrands) {
        update_post_meta( $setbrands[0]->ID, 'metaprice', $price);
    } else { // Иначе создаем новую запись типа "sets" и добавляем к ней мета-поле "metaprice" 
        $args = array(
            'post_title' => 'Набор товаров: ' . $brand_name,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'sets',
            'meta_input'   => array(
                'metaprice' => $price,
            ),
        );

        // Вставляем новую запись типа "sets" в БД и сохраняем ее ID в переменную $setID
        $setID = wp_insert_post($args);

        // Если идентификатор термина "brands" для данной записи не пустой, то добавляем этот термин к записи типа "sets" с помощью функции wp_set_object_terms
        if($brand_id) {
            wp_set_object_terms($setID, $brand_id, 'brands');
        }
    }
}

// Добавляем хук, который будет вызывать функцию updatePrice при изменении мета-поля 'metaprice' записи 
add_action('updated_post_meta', 'updatePrice', 0, 4);
// Определяем функцию, которая будет выполняться после изменения мета-поля 'metaprice' записи
function updatePrice($meta_id, $post_id, $meta_key, $meta_value) {
    // Проверяем, что ключ мета-поля равен 'metaprice'
    if( 'metaprice' == $meta_key ) {
        // Вызываем функцию srtUpdatePrice для обновления цены у всех наборов товаров, содержащих данную запись
        srtUpdatePrice($post_id);
    }
}

// Добавляем хук, который будет вызывать функцию updetaPriceIfDelete при удалении записи 
add_action( 'trashed_post', 'updetaPriceIfDelete');
// Определяем функцию, которая будет обновлять цену набора товаров при удалении записи
function updetaPriceIfDelete( $post_id ) {
    srtUpdatePrice($post_id); // Вызываем функцию обновления цены при удалении записи
}


// Добавляем фильтр, который будет применять функцию setsContent к контенту поста
add_filter('the_content', 'setsContent');
// Определяем функцию, которая будет генерировать контент для новых записей типа "sets" и "products"
function setsContent() {
    global $post;
    // Проверяем, является ли текущая страница постом или страницей
    if( is_singular() ) {
         // Проверяем, является ли тип текущего поста 'sets'
        if( 'sets' == $post->post_type ) {
            // Получаем метку бренда, которой отмечен текущий пост
            $product_brand = wp_get_post_terms($post->ID, 'brands');
            if($product_brand) {
                // Получаем список продуктов, которые отмечены той же меткой бренда
                $products_in_set = postsInBrans('products', $product_brand[0]->term_id);

                if($products_in_set) {
                    // Создаем заголовок для списка продуктов
                    $content = '<h2>В данный набор входит:</h2>';
                    $content .= '<ul>';
                     // Добавляем каждый продукт в список
                    foreach ($products_in_set as $product) {
                        $content .= '<li><a href="'.get_permalink($product->ID).'">' . $product->post_title . '</a></li>';
                    }
                    $content .= '</ul>';
                     // Добавляем заголовок и цену набора
                    $content .= '<h3>Стоимость со скидкой 20%:</h3>';
                    $content .= '<span>' . number_format(get_post_meta($post->ID, 'metaprice')[0]) . '</span>';
                } else {
                    // Если продуктов нет, выводим сообщение
                    $content = __("Здесь еще нет товаров");
                }
                // Возвращаем контент с списком продуктов и ценой набора
                return $content;
            }
        }
        // Проверяем, является ли тип текущего поста 'products'
        if( 'products' == $post->post_type ) {
            // Получаем метку бренда, которой отмечен текущий пост
            $product_brand = wp_get_post_terms($post->ID, 'brands');
            if($product_brand) {
                // Добавляем заголовок и цену продукта
                $content .= '<h3>Цена:</h3>';
                $content .= '<span>' . number_format(get_post_meta($post->ID, 'metaprice')[0]) . '</span>';
                // Возвращаем контент с ценой продукта
                return $content;
            }

        }
        // Если тип поста не 'sets' или 'products', возвращаем пустой контент
        return;
    }
}


// Определяем функцию, которая получает все записи заданного типа, которые относятся к указанной метке бренда
function postsInBrans(string $type, int $brand_id): array {
    // Возвращает список записей, соответствующих заданным параметрам
    return get_posts([
        'post_type' => $type,
        'numberposts' => -1,
        'post_status'=>'publish',
        'tax_query' => [
            [
                'taxonomy' => 'brands',
                'field'    => 'term_id',
                'terms'    => $brand_id
            ]
        ],
    ]);
}


// Определяем функцию для расчета скидки на товары в заданной категории
function calculateDiscount(int $brand_id, mixed $discount = 0.2): mixed {
    // Получение всех товаров из категории
    $products_in_set = postsInBrans('products', $brand_id);
    // Инициализация переменной для хранения суммарной стоимости товаров в категории
    $price = 0;
    // Суммирование стоимости всех товаров в категории
    foreach ($products_in_set as $product) {
        $price += get_post_meta($product->ID, 'metaprice')[0];
    }
    // Вычисление скидки и возврат итоговой стоимости товаров с учетом скидки
    return $price - $price * $discount;
}

function srtUpdatePrice($post_id) {
    // Получаем список брендов товара
    $product_brands = wp_get_post_terms($post_id, 'brands');
    // Получаем ID бренда товара
    $brand_id = $product_brands[0]->term_id ?: null;
    // Получаем список наборов с этим брендом
    $setbrands = postsInBrans('sets', $brand_id);
     // Вычисляем скидку на основе бренда и товаров в наборе
    $price = calculateDiscount($brand_id);
    // Если есть наборы с этим брендом, то обновляем цену на первый набор
    if($setbrands) {
        update_post_meta( $setbrands[0]->ID, 'metaprice', $price);
    }
}


