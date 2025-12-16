import { useState, useMemo } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  Search,
  Book,
  ChevronRight,
  Rocket,
  Cable,
  Users,
  FileText,
  Settings,
  Copy,
  Check,
  PlayCircle,
} from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { documentationData } from './data'
import type { DocArticle } from './types'
import { useOnboardingStore } from '@/stores/useOnboardingStore'

const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
  Rocket,
  Cable,
  Users,
  FileText,
  Settings,
  Book,
}

function getIcon(iconName: string) {
  return iconMap[iconName] || Book
}

export default function DocsIndex() {
  const [searchQuery, setSearchQuery] = useState('')
  const [copiedLink, setCopiedLink] = useState<string | null>(null)
  const navigate = useNavigate()
  const { startTour, hasCompletedTour } = useOnboardingStore()

  const filteredData = useMemo(() => {
    if (!searchQuery.trim()) return documentationData

    const query = searchQuery.toLowerCase()
    return documentationData
      .map((category) => ({
        ...category,
        articles: category.articles.filter(
          (article) =>
            article.title.toLowerCase().includes(query) ||
            article.description.toLowerCase().includes(query) ||
            article.content.toLowerCase().includes(query)
        ),
      }))
      .filter((category) => category.articles.length > 0)
  }, [searchQuery])

  const allArticles = useMemo(() => {
    return documentationData.flatMap((category) =>
      category.articles.map((article) => ({
        ...article,
        categoryId: category.id,
        categoryTitle: category.title,
      }))
    )
  }, [])

  const searchResults = useMemo(() => {
    if (!searchQuery.trim()) return []
    const query = searchQuery.toLowerCase()
    return allArticles.filter(
      (article) =>
        article.title.toLowerCase().includes(query) ||
        article.description.toLowerCase().includes(query)
    )
  }, [searchQuery, allArticles])

  const copyShareLink = (categoryId: string, articleId: string) => {
    const url = `${window.location.origin}/docs/${categoryId}/${articleId}`
    navigator.clipboard.writeText(url)
    setCopiedLink(articleId)
    setTimeout(() => setCopiedLink(null), 2000)
  }

  const handleArticleClick = (categoryId: string, articleId: string) => {
    navigate(`/docs/${categoryId}/${articleId}`)
  }

  return (
    <FloatingLayout>
      <div className="min-h-screen bg-gradient-to-b from-background to-muted/20">
        {/* Hero Section */}
        <div className="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
          <div className="container mx-auto px-4 py-12 md:py-16">
            <div className="max-w-3xl mx-auto text-center">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/10 mb-6">
                <Book className="w-8 h-8 text-primary" />
              </div>
              <h1 className="text-4xl md:text-5xl font-bold tracking-tight mb-4">
                Documentation & Guides
              </h1>
              <p className="text-lg text-muted-foreground mb-6">
                Everything you need to know about using the reporting platform. Search for topics or
                browse by category.
              </p>

              {/* Take the Tour Button */}
              <div className="mb-8">
                <Button
                  onClick={() => {
                    navigate('/platform/dashboard')
                    setTimeout(() => startTour(), 300)
                  }}
                  variant={hasCompletedTour ? 'outline' : 'default'}
                  size="lg"
                  className="gap-2"
                >
                  <PlayCircle className="h-5 w-5" />
                  {hasCompletedTour ? 'Retake the Guided Tour' : 'Take the Guided Tour'}
                </Button>
              </div>

              {/* Search */}
              <div className="relative max-w-xl mx-auto">
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-muted-foreground" />
                <Input
                  type="search"
                  placeholder="Search documentation..."
                  className="pl-12 h-12 text-base rounded-xl border-2 focus:border-primary"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>

              {/* Search Results Dropdown */}
              {searchQuery && searchResults.length > 0 && (
                <div className="absolute left-1/2 -translate-x-1/2 w-full max-w-xl mt-2 bg-background border rounded-xl shadow-lg z-50 max-h-96 overflow-y-auto">
                  {searchResults.slice(0, 8).map((article) => (
                    <button
                      key={`${article.categoryId}-${article.id}`}
                      onClick={() => handleArticleClick(article.categoryId, article.id)}
                      className="w-full px-4 py-3 text-left hover:bg-muted/50 flex items-center gap-3 border-b last:border-b-0"
                    >
                      <FileText className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                      <div className="min-w-0">
                        <p className="font-medium truncate">{article.title}</p>
                        <p className="text-sm text-muted-foreground truncate">
                          {article.categoryTitle}
                        </p>
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Categories Grid */}
        <div className="container mx-auto px-4 py-12">
          {filteredData.length === 0 ? (
            <div className="text-center py-12">
              <p className="text-lg text-muted-foreground">
                No results found for "{searchQuery}"
              </p>
              <Button variant="link" onClick={() => setSearchQuery('')}>
                Clear search
              </Button>
            </div>
          ) : (
            <div className="space-y-12">
              {filteredData.map((category) => {
                const CategoryIcon = getIcon(category.icon)
                return (
                  <section key={category.id}>
                    <div className="flex items-center gap-3 mb-6">
                      <div className="p-2 rounded-lg bg-primary/10">
                        <CategoryIcon className="h-5 w-5 text-primary" />
                      </div>
                      <div>
                        <h2 className="text-2xl font-semibold">{category.title}</h2>
                        <p className="text-muted-foreground">{category.description}</p>
                      </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                      {category.articles.map((article) => (
                        <ArticleCard
                          key={article.id}
                          article={article}
                          categoryId={category.id}
                          onCopyLink={() => copyShareLink(category.id, article.id)}
                          isCopied={copiedLink === article.id}
                        />
                      ))}
                    </div>
                  </section>
                )
              })}
            </div>
          )}
        </div>

        {/* Quick Links Footer */}
        <div className="border-t bg-muted/30">
          <div className="container mx-auto px-4 py-8">
            <div className="flex flex-wrap justify-center gap-4 text-sm text-muted-foreground">
              <span>Need more help?</span>
              <Link to="/admin/settings" className="text-primary hover:underline">
                Contact Support
              </Link>
              <span>•</span>
              <Link to="/platform/dashboard" className="text-primary hover:underline">
                Back to Dashboard
              </Link>
            </div>
          </div>
        </div>
      </div>
    </FloatingLayout>
  )
}

interface ArticleCardProps {
  article: DocArticle
  categoryId: string
  onCopyLink: () => void
  isCopied: boolean
}

function ArticleCard({ article, categoryId, onCopyLink, isCopied }: ArticleCardProps) {
  return (
    <Card className="group hover:shadow-md transition-all duration-200 hover:border-primary/50">
      <Link to={`/docs/${categoryId}/${article.id}`}>
        <CardHeader className="pb-2">
          <div className="flex items-start justify-between">
            <CardTitle className="text-lg group-hover:text-primary transition-colors">
              {article.title}
            </CardTitle>
            <ChevronRight className="h-5 w-5 text-muted-foreground group-hover:text-primary group-hover:translate-x-1 transition-all" />
          </div>
          <CardDescription className="line-clamp-2">{article.description}</CardDescription>
        </CardHeader>
      </Link>
      <CardContent className="pt-0">
        <div className="flex items-center justify-between">
          {article.relatedArticles && article.relatedArticles.length > 0 && (
            <Badge variant="secondary" className="text-xs">
              {article.relatedArticles.length} related
            </Badge>
          )}
          <Button
            variant="ghost"
            size="sm"
            className="ml-auto h-8 px-2 text-muted-foreground hover:text-foreground"
            onClick={(e) => {
              e.preventDefault()
              onCopyLink()
            }}
          >
            {isCopied ? (
              <>
                <Check className="h-3.5 w-3.5 mr-1" />
                Copied
              </>
            ) : (
              <>
                <Copy className="h-3.5 w-3.5 mr-1" />
                Share
              </>
            )}
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
