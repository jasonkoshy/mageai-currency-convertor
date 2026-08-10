<?php
declare(strict_types=1);

namespace MageAI\CurrencyConverter\Controller\Rates;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use MageAI\CurrencyConverter\Model\FrankfurterClient;
use Psr\Log\LoggerInterface;

class Currencies implements HttpGetActionInterface
{
    /**
     * 
     * @param JsonFactory       $resultJsonFactory
     * @param FrankfurterClient $frankfurterClient
     * @param LoggerInterface   $logger
     */
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly FrankfurterClient $frankfurterClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Load the currencies
     * 
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $currencies = $this->frankfurterClient->getCurrencies();

            return $result->setData(
                [
                'success' => true,
                'currencies' => $currencies,
                ]
            );
        } catch (LocalizedException $e) {
            $this->logger->warning('MageAI_CurrencyConverter: ' . $e->getMessage());

            return $result->setHttpResponseCode(502)->setData(
                [
                'success' => false,
                'message' => $e->getMessage(),
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('MageAI_CurrencyConverter: ' . $e->getMessage());

            return $result->setHttpResponseCode(500)->setData(
                [
                'success' => false,
                'message' => (string)__('Unable to load currencies right now. Please try again later.'),
                ]
            );
        }
    }
}
