.PHONY: about qa sa cs fix lint test help phpunit phpstan php-cs-fixer phpcsfixer

.DEFAULT_GOAL := help

ARGS ?=
PHP_CMD ?= php

get_args = $(if $(filter-out $1,$(MAKECMDGOALS)),$(filter-out $1,$(MAKECMDGOALS)),$(ARGS))

define run_cmd
@echo "[·] \033[2m$(1) $(2)\033[0m\n"
@start_time=$$(date +%s.%N); \
$(1) $(2); \
exit_code=$$?; \
end_time=$$(date +%s.%N); \
elapsed_time=$$(echo "$$end_time - $$start_time" | bc | awk '{printf "%.2f", $$1}'); \
command="$(1) $(2)"; \
if [ $$exit_code -eq 0 ]; then \
  printf "\n[\033[32m✓\033[0m] \033[2m%s\033[0m\033[116G\033[2m[\033[0m\033[32m%s\033[0m \033[2mseconds\033[0m\033[2m]\033[0m\n" "$$command" "$$elapsed_time"; \
else \
  printf "\n[\033[31m✗\033[0m] \033[2m%s\033[0m\033[116G\033[2m[\033[0m\033[31m%s\033[0m \033[2mseconds\033[0m\033[2m]\033[0m\n" "$$command" "$$elapsed_time"; \
fi
endef

define php_cmd
	$(call run_cmd,$(PHP_CMD) $(1),$(2))
endef

GIT_VERSION  := $(shell git describe --tags --abbrev=0 2>/dev/null || echo "dev-main")
PHP_VERSION  := $(shell php -r 'echo PHP_VERSION;' 2>/dev/null || echo "n/a")
PROJECT_NAME := TWIG METRICS
REPO_URL     := https://github.com/smnandre/twigmetrics

about:
	@echo "┌───────────────────────────── 🌿  ────────────────────────────────┐"; \
	printf "│%*s\033[38;5;113m%s\033[0m \033[38;5;252m%s\033[0m%*s│\n" 26 "" "TWIG " "METRICS" 26 ""; \
	echo "│                                                                 │"; \
	printf "│ %-63s │\n" "$(REPO_URL)"; \
	echo "│                                                                 │"; \
	printf "│ %-9s \033[36m%-21s\033[0m  %-9s \033[36m%-21s\033[0m │\n" "Author:" "Simon André"  "Version:" "$(GIT_VERSION)"; \
	printf "│ %-9s \033[36m%-20s\033[0m  %-9s \033[36m%-21s\033[0m │\n" "License:" "MIT " "PHP:  " "$(PHP_VERSION)"; \
	echo "└─────────────────────────────────────────────────────────────────┘"

warning:
	@echo ""
	@echo "  \033[33m⚠︎\033[0m \033[2mThis Makefile is intended for \033[0;0mTwig Metrics \033[33mdevelopment\033[2m.\033[0m"
	@echo "  \033[2mIt includes tools and commands for quality checks and tests.\033[0m"
	@echo "  \033[2mEnd users \033[0mdo not\033[2m need to run these commands.\033[0m"
	@echo ""

help: about
	@echo ""
	@echo " Usage:"
	@echo "    \033[33mmake \033[38;5;113mphpunit\033[0m\033[33m ARGS=\"\033[38;5;113m--testdox\033[33m\"\033[0m"
	@echo ""
	@echo " Tools:"
	@echo "   make \033[38;5;113mphpunit\033[0m      	  Run PHPUnit\033[0m"
	@echo "   make \033[38;5;113mphpstan\033[0m      	  Run PHPStan\033[0m"
	@echo "   make \033[38;5;113mphp-cs-fixer\033[0m 	  Run PHP-CS-Fixer\033[0m"
	@echo ""
	@echo " Commands:"
	@echo "   make \033[38;5;113mfix\033[0m		  Run fixers (\033[32mphp-cs-fixer\033[0m)"
	@echo "   make \033[38;5;113mlint\033[0m		  Run linters (\033[32mphp-cs-fixer\033[0m)"
	@echo "   make \033[38;5;113mtest\033[0m		  Run tests (\033[32mphpunit\033[0m)"
	@echo "   make \033[38;5;113msa\033[0m		  Run static analysis (\033[32mphpstan\033[0m)"
	@echo ""
	@echo " All-in:"
	@echo "   make \033[92mqa\033[0m       	  Run \033[32mfix\033[0m, \033[32mlint\033[0m, \033[32msa\033[0m, \033[32mtest\033[0m"
	@make warning	

phpcsfixer:
	$(call php_cmd,vendor/bin/php-cs-fixer fix,$(call get_args,$@))

php-cs-fixer:
	@$(MAKE) phpcsfixer ARGS="$(ARGS)"

cs:
	@$(MAKE) lint

phpstan:
	$(call php_cmd,vendor/bin/phpstan analyse -- src,$(call get_args,$@))

phpunit:
	$(call php_cmd,vendor/bin/phpunit,$(call get_args,$@))

fix:
	@$(MAKE) phpcsfixer ARGS="--diff"

lint:
	@$(MAKE) phpcsfixer ARGS="--diff --dry-run"

sa: 
	@$(MAKE) phpstan

test: 
	@$(MAKE) phpunit

qa: 
	@$(MAKE) fix
	@$(MAKE) lint
	@$(MAKE) sa
	@$(MAKE) test

%:
	@:
