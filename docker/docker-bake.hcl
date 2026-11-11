#   docker buildx bake -f docker/docker-bake.hcl --set '*.platform=linux/arm64'
variable "REGISTRY" { default = "localhost:5000" }
variable "TAGS" { default = "dev" }
variable "GIT_COMMIT_HASH" { default = "unknown" } // full 40-hex sha
variable "GIT_COMMIT_DATE" { default = "unknown" } // ISO 8601

group "default" { targets = ["fpm", "web"] }

target "_common" {
  context    = "."
  dockerfile = "docker/Dockerfile"
  platforms  = ["linux/amd64", "linux/arm64"]
  args = {
    GIT_COMMIT_HASH = GIT_COMMIT_HASH
    GIT_COMMIT_DATE = GIT_COMMIT_DATE
  }
}

target "fpm" {
  inherits = ["_common"]
  target   = "fpm"
  tags     = [for t in split(",", TAGS) : "${REGISTRY}/personalsite-fpm:${t}"]
}
target "web" {
  inherits = ["_common"]
  target   = "web"
  tags     = [for t in split(",", TAGS) : "${REGISTRY}/personalsite-web:${t}"]
}
