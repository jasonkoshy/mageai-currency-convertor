<?php
declare(strict_types=1);

namespace MageAI\CurrencyConverter\Block;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Converter extends Template
{
    private const DEFAULT_FROM_CURRENCY = 'USD';
    private const DEFAULT_TO_CURRENCY = 'EUR';

    /**
     * 
     * @param Context $context
     * @param Json    $json
     * @param array   $data
     */
    public function __construct(
        Context $context,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get CurrenciesUrl
     * 
     * @return string
     */
    public function getCurrenciesUrl(): string
    {
        return $this->getUrl('currency-converter/rates/currencies');
    }

    /**
     * Get RateUrl
     * 
     * @return string
     */
    public function getRateUrl(): string
    {
        return $this->getUrl('currency-converter/rates/rate');
    }

    /**
     * Get HistoryUrl
     * 
     * @return string
     */
    public function getHistoryUrl(): string
    {
        return $this->getUrl('currency-converter/rates/history');
    }

    /**
     * Get DefaultFromCurrency
     * 
     * @return string
     */
    public function getDefaultFromCurrency(): string
    {
        return (string)($this->getData('default_from_currency') ?: self::DEFAULT_FROM_CURRENCY);
    }

    /**
     * Get DefaultToCurrency
     * 
     * @return string
     */
    public function getDefaultToCurrency(): string
    {
        return (string)($this->getData('default_to_currency') ?: self::DEFAULT_TO_CURRENCY);
    }

    /**
     * Generate the JSON configuration 
     * The configuration contains the endpoint URLs used to retrieve supported 
     * currencies, exchange rates, and exchange rate history, along with the 
     * default source and target currencies. 
     * 
     * @return string JSON-encoded widget configuration containing API endpoint
     * URLs and default currency values.
     */
    public function getWidgetConfigJson(): string
    {
        return $this->json->serialize(
            [
            'currenciesUrl' => $this->getCurrenciesUrl(),
            'rateUrl' => $this->getRateUrl(),
            'historyUrl' => $this->getHistoryUrl(),
            'defaultFrom' => $this->getDefaultFromCurrency(),
            'defaultTo' => $this->getDefaultToCurrency(),
            ]
        );
    }
}
