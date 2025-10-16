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
     * Inicializa o produto doação.
     */
    public function __construct($product = 0)
    {
        parent::__construct($product);
        $this->product_type = 'donation';
    }

    /**
     * Retorna o tipo do produto.
     */
    public function get_type()
    {
        return 'donation';
    }
}