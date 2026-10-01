FROM php:8.2-cli

# Install pdo_mysql and other extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install additional extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install mbstring gd

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Expose port
EXPOSE 8080

# Start PHP server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
