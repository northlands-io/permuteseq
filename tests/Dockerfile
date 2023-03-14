FROM postgres:14.4-alpine

RUN apk add --no-cache \
      ca-certificates \
      openssl \
      unzip \
      autoconf \
      automake \
      g++ \
      gcc  \
      make \
      clang \
      clang-dev \
      llvm \
    && wget -O permuteseq.zip https://github.com/dverite/permuteseq/archive/refs/tags/v1.2.1.zip \
    && mkdir -p /usr/src/permuteseq \
    && unzip permuteseq.zip -d /usr/src/permuteseq \
    && rm permuteseq.zip \
    && cd /usr/src/permuteseq/permuteseq-1.2.1 \
    && make \
    && make install
