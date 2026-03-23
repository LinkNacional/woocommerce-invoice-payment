<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de produto para o tipo "Doação".
 * 
 * Nota: O WooCommerce exige que classes de produtos personalizados 
 * sigam a convenção WC_Product_{tipo} para funcionar corretamente.
 */
class WC_Product_Donation extends WC_Product_Simple
{
    
    /**
     * Tipo de produto.
     *
     * @var string
     */
    protected $product_type = 'donation';

    /**
     * Inicializa o produto doação.
     *
     * @param int|object $product Produto ou ID do produto.
     */
    public function __construct($product = 0)
    {
        parent::__construct($product);
    }

    /**
     * Retorna o tipo do produto.
     * 
     * @return string
     */
    public function get_type()
    {
        return 'donation';
    }
}