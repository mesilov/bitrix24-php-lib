FROM postgres:16.4-alpine

# Определяем ARG переменные, которые будут переданы при сборке
ARG POSTGRES_USER
ARG POSTGRES_PASSWORD
ARG POSTGRES_DB

# Устанавливаем переменные окружения для настройки базы данных
ENV POSTGRES_USER=${POSTGRES_USER}
ENV POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
ENV POSTGRES_DB=${POSTGRES_DB}

# Копируем SQL-скрипты для инициализации базы данных (если есть)
# COPY init.sql /docker-entrypoint-initdb.d/

# Открываем порт PostgreSQL
EXPOSE 5432




