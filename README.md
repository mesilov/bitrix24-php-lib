# bitrix24-php-lib

PHP lib for Bitrix24 application development

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
хранение [партнёра](https://github.com/mesilov/bitrix24-php-sdk/tree/master/src/Application/Contracts/Bitrix24Partners) Битрикс24, который произвёл установку или обслуживает портал

## Архитектура

### Слои и уровни абстракции
```
bitrix24-app-laravel-skeleton – шаблон приложения на Laravel
bitrix24-app-symfony-skeleton – шаблон приложения на Symfony    
bitrix24-php-lib – работа с сущностями приложения и их хранение в СУБД
bitrix24-php-sdk – транспортный слой + события транспорта (протух токен, переименовали портал)
```

### Структура папок bounded context
```
src/
    Bitrix24Accounts
        Controllers
        Entity
        Exceptions
        Events
        EventListeners
        Infrastructure
            ConsoleCommands
            Doctrine
                Types
        Repository
        ReadModel
        UseCases
            SomeUseCase
        Tests    
```


## Инфраструктура
- библиотека делается cloud-agnostic


## Правила разработки
1. Используем линтеры
2. Библиотека покрыта тестами
3. Вся работа строится через issues
4. Процессы разработки - remote first
5. Думаем и обсуждаем — потом пишем