# Imagen base con Node
FROM node:20

WORKDIR /app

# Copiar package.json y package-lock.json para instalar deps
COPY package*.json ./
RUN npm install

# Copiar todo el código
COPY . .

# Construir la app
RUN npm run build

# Servir con un servidor ligero (por ejemplo, serve)
RUN npm install -g serve

EXPOSE 5173

CMD ["serve", "-s", "dist", "-l", "5173"]

