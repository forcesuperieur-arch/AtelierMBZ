FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci || npm install

COPY . .
RUN npx nuxt build

FROM node:22-alpine

WORKDIR /app
ENV NODE_ENV=production
ENV PORT=3001

COPY --from=build /app/.output ./.output

EXPOSE 3001

CMD ["node", ".output/server/index.mjs"]
