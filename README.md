# bitrix24-app-core

Bitrix24 application core

## Область применения

Библиотека предназначена для быстрой разработки приложений для Битркис24. Предоставляет слой хранения данных в СУБД
[PostgreSQL](https://www.postgresql.org/), использует [Doctrine ORM](https://www.doctrine-project.org/).

Реализует [контракты](https://github.com/mesilov/bitrix24-php-sdk/tree/master/src/Application/Contracts) из
bitrix24-php-sdk.

## Поддерживаемые контракты

### Bitrix24Accounts

Отвечает за
хранение [аккаунтов Битрикс24](https://github.com/mesilov/bitrix24-php-sdk/tree/master/src/Application/Contracts/Bitrix24Accounts)
с токенами доступа к порталу.

### ApplicationInstallations

Отвечает за
хранение [фактов установок](https://github.com/mesilov/bitrix24-php-sdk/tree/master/src/Application/Contracts/ApplicationInstallations)
приложения на конкретный портал Битркис24

### ContactPersons

Отвечает за
хранение [контактных лиц](https://github.com/mesilov/bitrix24-php-sdk/tree/master/src/Application/Contracts/ContactPersons),
которые произвели установку приложения

### Bitrix24Partners

Отвечает за
хранение [партнёра](https://github.com/mesilov/bitrix24-php-sdk/tree/master/src/Application/Contracts/Bitrix24Partners
) Битрикс24, который произвёл установку или обслуживает портал



   
