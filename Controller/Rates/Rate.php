<?php
declare(strict_types=1);

namespace MageAI\CurrencyConverter\Controller\Rates;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use MageAI\CurrencyConverter\Model\FrankfurterClient;
use Psr\Log\LoggerInterface;

class Rate implements HttpGetActionInterface
{
    /**
     * 
     * @param RequestInterface  $request
     * @param JsonFactory       $resultJsonFactory
     * @param FrankfurterClient $frankfurterClient
     * @param LoggerInterface   $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FrankfurterClient $frankfurterClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Load the exchange rate
     * 
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        $base = (string)$this->request->getParam('base', '');
        $quote = (string)$this->request->getParam('quote', '');

        //Request Validation
        if ($base === '' || $quote === '') {
            return $result->setHttpResponseCode(400)->setData(
                [
                'success' => false,
                'message' => (string)__('Both "base" and "quote" currency codes are required.'),
                ]
            );
        }

        try {
            //Currency rate calculation using frankfurter API
            $rate = $this->frankfurterClient->getRate($base, $quote);

            return $result->setData(
                [
                'success' => true,
                'rate' => $rate,
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
                'message' => (string)__('Unable to load the exchange rate right now. Please try again later.'),
                ]
            );
        }
    }
}
