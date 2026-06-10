FROM node:22-alpine

WORKDIR /app

COPY package.json ./
RUN npm install

COPY . .

EXPOSE 3001

CMD ["npx", "nuxt", "dev", "--port", "3001", "--host", "0.0.0.0"]
