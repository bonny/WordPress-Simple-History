FROM node:20-bookworm

# Install minimal system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    php-cli \
    php-xml \
    php-mysql \
    sudo \
    jq \
    html2text \
    && rm -rf /var/lib/apt/lists/*

# Install html2markdown (html2md from suntong/html2md) - multi-arch
RUN ARCH=$(dpkg --print-architecture) && \
    if [ "$ARCH" = "amd64" ]; then ARCH_NAME="amd64"; \
    elif [ "$ARCH" = "arm64" ]; then ARCH_NAME="arm64"; \
    else echo "Unsupported architecture: $ARCH" && exit 1; fi && \
    curl -fsSL "https://github.com/suntong/html2md/releases/download/v1.6.0/html2md_1.6.0_linux_${ARCH_NAME}.tar.gz" \
    | tar -xz --strip-components=1 -C /usr/local/bin \
    && mv /usr/local/bin/html2md /usr/local/bin/html2markdown

# Note: htmlq is not installed because it requires rustc 1.83+ which isn't available
# in Debian Bookworm repos. Use grep or pup as alternatives for CSS selector extraction.

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Install GitHub CLI
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg \
    && chmod go+r /usr/share/keyrings/githubcli-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | tee /etc/apt/sources.list.d/github-cli.list > /dev/null \
    && apt-get update \
    && apt-get install -y gh \
    && rm -rf /var/lib/apt/lists/*

# Configure node user (already exists in node:20 image with UID 1000)
# Give node user sudo access and create required directories
RUN echo "node ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers && \
    mkdir -p /workspace /home/node/.claude /home/node/.local/bin /home/node/.wp-cli && \
    chown -R node:node /workspace /home/node/.claude /home/node/.local /home/node/.wp-cli

# Switch to node user to install Claude Code natively (installs to ~/.local/bin)
USER node
RUN curl -fsSL https://claude.ai/install.sh | bash
USER root

# Create entrypoint script inline to avoid permission issues
RUN printf '#!/bin/bash\nsudo chown -R $(id -u):$(id -g) /home/node/.claude 2>/dev/null || true\nexec "$@"\n' > /entrypoint.sh && \
    chmod +x /entrypoint.sh

# Set environment variables
ENV DEVCONTAINER=true
ENV TERM=xterm-256color
ENV COLORTERM=truecolor
ENV PATH="/home/node/.local/bin:${PATH}"

# Switch to non-root user
USER node

WORKDIR /workspace

ENTRYPOINT ["/entrypoint.sh"]
CMD ["bash"]
