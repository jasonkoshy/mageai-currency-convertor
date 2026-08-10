<?php
declare(strict_types=1);

namespace MageAI\CurrencyConverter\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Business Logic
 * Server-side proxy for the Frankfurter exchange rate API (https://frankfurter.dev).
 */
class FrankfurterClient
{
    private const API_BASE = 'https://api.frankfurter.dev/v2';
    private const CACHE_TAG = 'MAGEAI_CURRENCYCONVERTER';

    /**
     * TTLs, in seconds, for the different endpoints. 
     */
    private const TTL_CURRENCIES = 86400; // 24h
    private const TTL_RATE = 900;         // 15 min
    private const TTL_HISTORY = 3600;     // 1h

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array List of {iso_code, name, symbol, start_date, end_date}
     * @throws LocalizedException
     */
    public function getCurrencies(): array
    {
        return $this->request('/currencies', [], self::TTL_CURRENCIES, 'currencies');
    }

    /**
     * @param  string $base  Three letter ISO currency code
     * @param  string $quote Three letter ISO currency code
     * @return array {date, base, quote, rate}
     * @throws LocalizedException
     */
    public function getRate(string $base, string $quote): array
    {
        $base = $this->assertCurrencyCode($base);
        $quote = $this->assertCurrencyCode($quote);

        return $this->request(
            '/rate/' . $base . '/' . $quote,
            [],
            self::TTL_RATE,
            'rate_' . $base . '_' . $quote
        );
    }

    /**
     * @param  string $base  Three letter ISO currency code
     * @param  string $quote Three letter ISO currency code
     * @param  string $from  Date in YYYY-MM-DD format
     * @param  string $to    Date in YYYY-MM-DD format
     * @return array List of {date, base, quote, rate}
     * @throws LocalizedException
     */
    public function getHistory(string $base, string $quote, string $from, string $to): array
    {
        $base = $this->assertCurrencyCode($base);
        $quote = $this->assertCurrencyCode($quote);
        $from = $this->assertDate($from);
        $to = $this->assertDate($to);

        return $this->request(
            '/rates',
            ['base' => $base, 'quotes' => $quote, 'from' => $from, 'to' => $to],
            self::TTL_HISTORY,
            'history_' . $base . '_' . $quote . '_' . $from . '_' . $to
        );
    }

    /**
     * @throws LocalizedException
     */
    private function assertCurrencyCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            throw new LocalizedException(__('Invalid currency code.'));
        }

        return $code;
    }

    /**
     * @throws LocalizedException
     */
    private function assertDate(string $date): string
    {
        $date = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new LocalizedException(__('Invalid date.'));
        }

        return $date;
    }

    /**
     * Retrieve exchange rate data from Frankfurter API using Curl
     * 
     * @throws LocalizedException
     */
    private function request(string $path, array $params, int $ttl, string $cacheKeySuffix): array
    {
        $cacheKey = self::CACHE_TAG . '_' . md5($cacheKeySuffix);
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            return $this->json->unserialize($cached);
        }

        $url = self::API_BASE . $path;
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        /**
 * @var Curl $curl 
*/
        $curl = $this->curlFactory->create();
        $curl->setOption(CURLOPT_TIMEOUT, 8);
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
        $curl->addHeader('Accept', 'application/json');

        try {
            $curl->get($url);
        } catch (\Throwable $e) {
            $this->logger->warning(
                sprintf(
                    'MageAI_CurrencyConverter: outbound request to Frankfurter threw an exception. url=%s error=%s',
                    $url,
                    $e->getMessage()
                )
            );

            throw new LocalizedException(
                __('Unable to retrieve exchange rate data at this time. Please try again shortly.')
            );
        }

        $status = $curl->getStatus();
        $body = $curl->getBody();
        $curlError = method_exists($curl, 'getErrno') ? $curl->getErrno() : null;

        if ($status !== 200 || !$body) {
            $this->logger->warning(
                sprintf(
                    'MageAI_CurrencyConverter: Frankfurter API call failed. url=%s http_status=%s curl_errno=%s body_length=%d',
                    $url,
                    $status ?: 'n/a',
                    $curlError ?: 'n/a',
                    strlen((string)$body)
                )
            );

            throw new LocalizedException(
                __('Unable to retrieve exchange rate data at this time. Please try again shortly.')
            );
        }

        $data = $this->json->unserialize($body);
        $this->cache->save($this->json->serialize($data), $cacheKey, [self::CACHE_TAG], $ttl);

        return $data;
    }
}
