# Imagen base con Node
FROM node:20

WORKDIR /app

# Copiar package.json y package-lock.json para instalar deps
COPY package*.json ./
RUN npm install

# Copiar entrypoint y convertir line endings
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && sed -i 's/\r$//' /entrypoint.sh

# Exponer puerto
EXPOSE 5173

ENTRYPOINT ["/entrypoint.sh"]

