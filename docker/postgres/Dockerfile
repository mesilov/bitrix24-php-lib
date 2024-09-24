FROM postgres:16.4-alpine

# Устанавливаем переменные окружения для настройки базы данных
ENV POSTGRES_USER=kirill
ENV POSTGRES_PASSWORD=bitrix24lib
ENV POSTGRES_DB=bitrixAppDb

# Копируем SQL-скрипты для инициализации базы данных (если есть)
# COPY init.sql /docker-entrypoint-initdb.d/

# Открываем порт PostgreSQL
EXPOSE 5432