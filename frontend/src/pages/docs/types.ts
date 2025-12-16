export interface DocArticle {
  id: string
  title: string
  description: string
  icon: string
  content: string
  relatedArticles?: string[]
}

export interface DocCategory {
  id: string
  title: string
  description: string
  icon: string
  articles: DocArticle[]
}
