<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Accounts\ValueObjects;

readonly class Domain
{
    public string $value;

    public function __construct(string $domain)
    {
        $this->validate($domain);
        $this->value = $domain;
    }

    /*
     * @return void
     *
     * Validates a domain name based on three main criteria:
     * 1. **Allowed characters**:
     *    - Latin, Cyrillic, digits, and hyphens.
     *    - Hyphens are prohibited at the start/end of each label (regex: `$patternValidChars`).
     *    - Supports IDN (Internationalized Domain Names).
     * 2. **Overall domain length**:
     *    - Not more than 255 characters (regex: `$patternLengthCheck`).
     * 3. **Length of each label**:
     *    - From 1 to 63 characters (regex: `$patternLengthEachLabel`).
     *    - Maximum number of labels: 127 (as per RFC 1035).
     *
     * Complies with the requirements of:
     * - RFC 1035 (basic domain name rules) [1].
     * - RFC 5891 (IDN) [2].
     *
     * Examples of valid domains:
     * - `example.com`
     * - `кириллический-домен.рф`
     *
     * Composed of three patterns for simplicity and clarity.
     * @throws \InvalidArgumentException If the domain fails any of the checks.
     *
     * [1] https://www.rfc-editor.org/rfc/rfc1035
     * [2] https://www.rfc-editor.org/rfc/rfc5891
     */
    private function validate(string $domain): void
    {
        // Regular expression for checking available symbol (Latin and Cyrillic)
        $patternValidChars = '/^((?!-)[A-Za-zА-Яа-яЁё0-9-]{1,63}(?<!-)\.)+[A-Za-zА-Яа-яЁё]{2,6}$/u';

        // Checking summary length (1-255 symbols)
        $patternLengthCheck = '/^.{1,255}$/';

        // Checking length each one label (1-63 symbol, include cyrillic)
        $patternLengthEachLabel = '/^[A-Za-zА-Яа-яЁё0-9-]{1,63}(\.[A-Za-zА-Яа-яЁё0-9-]{1,63}){0,126}$/u';
        if (
            in_array(preg_match($patternValidChars, $domain), [0, false], true)
            || in_array(preg_match($patternLengthCheck, $domain), [0, false], true)
            || in_array(preg_match($patternLengthEachLabel, $domain), [0, false], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid domain: %s', $domain));
        }
    }
}
