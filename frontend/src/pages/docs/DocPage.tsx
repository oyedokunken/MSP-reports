import { useMemo, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import ReactMarkdown from 'react-markdown'
import {
  ArrowLeft,
  ChevronRight,
  Copy,
  Check,
  ExternalLink,
  Menu,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet'
import { Separator } from '@/components/ui/separator'
import FloatingLayout from '@/components/layout/FloatingLayout'
import { documentationData } from './data'
import type { DocArticle } from './types'

export default function DocPage() {
  const { categoryId, articleId } = useParams<{ categoryId: string; articleId: string }>()
  const [copiedLink, setCopiedLink] = useState(false)
  const [sidebarOpen, setSidebarOpen] = useState(false)

  const { category, article, relatedArticles } = useMemo(() => {
    const cat = documentationData.find((c) => c.id === categoryId)
    const art = cat?.articles.find((a) => a.id === articleId)

    let related: Array<DocArticle & { categoryId: string; categoryTitle: string }> = []
    if (art?.relatedArticles) {
      related = art.relatedArticles
        .map((relId) => {
          for (const c of documentationData) {
            const found = c.articles.find((a) => a.id === relId)
            if (found) {
              return { ...found, categoryId: c.id, categoryTitle: c.title }
            }
          }
          return null
        })
        .filter(Boolean) as Array<DocArticle & { categoryId: string; categoryTitle: string }>
    }

    return { category: cat, article: art, relatedArticles: related }
  }, [categoryId, articleId])

  const copyShareLink = () => {
    const url = window.location.href
    navigator.clipboard.writeText(url)
    setCopiedLink(true)
    setTimeout(() => setCopiedLink(false), 2000)
  }

  if (!category || !article) {
    return (
      <FloatingLayout>
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <h1 className="text-2xl font-bold mb-4">Article Not Found</h1>
            <p className="text-muted-foreground mb-6">
              The documentation article you're looking for doesn't exist.
            </p>
            <Button asChild>
              <Link to="/docs">
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Documentation
              </Link>
            </Button>
          </div>
        </div>
      </FloatingLayout>
    )
  }

  const SidebarContent = () => (
    <div className="space-y-6">
      <div>
        <Link
          to="/docs"
          className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground mb-4"
        >
          <ArrowLeft className="h-4 w-4" />
          All Documentation
        </Link>
      </div>

      {documentationData.map((cat) => (
        <div key={cat.id}>
          <h3
            className={`font-semibold text-sm mb-2 ${
              cat.id === categoryId ? 'text-primary' : 'text-muted-foreground'
            }`}
          >
            {cat.title}
          </h3>
          <ul className="space-y-1">
            {cat.articles.map((art) => (
              <li key={art.id}>
                <Link
                  to={`/docs/${cat.id}/${art.id}`}
                  onClick={() => setSidebarOpen(false)}
                  className={`block text-sm py-1.5 px-3 rounded-md transition-colors ${
                    cat.id === categoryId && art.id === articleId
                      ? 'bg-primary/10 text-primary font-medium'
                      : 'text-muted-foreground hover:text-foreground hover:bg-muted'
                  }`}
                >
                  {art.title}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  )

  return (
    <FloatingLayout>
      <div className="min-h-screen bg-background">
        {/* Mobile Header */}
        <div className="sticky top-0 z-40 lg:hidden border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
          <div className="flex items-center justify-between px-4 py-3">
            <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon">
                  <Menu className="h-5 w-5" />
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="w-80 p-6">
                <SidebarContent />
              </SheetContent>
            </Sheet>
            <span className="font-medium truncate">{article.title}</span>
            <Button variant="ghost" size="icon" onClick={copyShareLink}>
              {copiedLink ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
            </Button>
          </div>
        </div>

        <div className="flex">
          {/* Desktop Sidebar */}
          <aside className="hidden lg:block w-72 shrink-0 border-r sticky top-0 h-screen overflow-hidden">
            <ScrollArea className="h-full py-6 px-4">
              <SidebarContent />
            </ScrollArea>
          </aside>

          {/* Main Content */}
          <main className="flex-1 min-w-0">
            <div className="max-w-4xl mx-auto px-4 py-8 lg:px-8 lg:py-12">
              {/* Breadcrumb */}
              <nav className="hidden lg:flex items-center gap-2 text-sm text-muted-foreground mb-6">
                <Link to="/docs" className="hover:text-foreground">
                  Documentation
                </Link>
                <ChevronRight className="h-4 w-4" />
                <Link to="/docs" className="hover:text-foreground">
                  {category.title}
                </Link>
                <ChevronRight className="h-4 w-4" />
                <span className="text-foreground">{article.title}</span>
              </nav>

              {/* Article Header */}
              <div className="mb-8">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <h1 className="text-3xl lg:text-4xl font-bold tracking-tight mb-3">
                      {article.title}
                    </h1>
                    <p className="text-lg text-muted-foreground">{article.description}</p>
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    className="hidden lg:flex shrink-0"
                    onClick={copyShareLink}
                  >
                    {copiedLink ? (
                      <>
                        <Check className="h-4 w-4 mr-2" />
                        Copied!
                      </>
                    ) : (
                      <>
                        <Copy className="h-4 w-4 mr-2" />
                        Share Link
                      </>
                    )}
                  </Button>
                </div>
              </div>

              <Separator className="mb-8" />

              {/* Article Content */}
              <article className="prose prose-slate dark:prose-invert max-w-none prose-headings:scroll-mt-20 prose-h1:text-2xl prose-h2:text-xl prose-h2:mt-8 prose-h2:mb-4 prose-h3:text-lg prose-h3:mt-6 prose-h3:mb-3 prose-p:leading-7 prose-li:leading-7 prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:before:content-none prose-code:after:content-none prose-pre:bg-muted prose-pre:border">
                <ReactMarkdown>{article.content}</ReactMarkdown>
              </article>

              {/* Related Articles */}
              {relatedArticles.length > 0 && (
                <div className="mt-12 pt-8 border-t">
                  <h2 className="text-xl font-semibold mb-4">Related Articles</h2>
                  <div className="grid gap-4 sm:grid-cols-2">
                    {relatedArticles.map((related) => (
                      <Card
                        key={related.id}
                        className="group hover:shadow-md transition-all hover:border-primary/50"
                      >
                        <Link to={`/docs/${related.categoryId}/${related.id}`}>
                          <CardHeader className="pb-2">
                            <div className="flex items-start justify-between">
                              <CardTitle className="text-base group-hover:text-primary transition-colors">
                                {related.title}
                              </CardTitle>
                              <ChevronRight className="h-4 w-4 text-muted-foreground group-hover:text-primary group-hover:translate-x-1 transition-all" />
                            </div>
                          </CardHeader>
                          <CardContent className="pt-0">
                            <p className="text-sm text-muted-foreground line-clamp-2">
                              {related.description}
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                              {related.categoryTitle}
                            </p>
                          </CardContent>
                        </Link>
                      </Card>
                    ))}
                  </div>
                </div>
              )}

              {/* Navigation */}
              <div className="mt-12 pt-8 border-t">
                <div className="flex items-center justify-between">
                  <Button variant="ghost" asChild>
                    <Link to="/docs">
                      <ArrowLeft className="mr-2 h-4 w-4" />
                      Back to Documentation
                    </Link>
                  </Button>
                  <Button variant="ghost" asChild>
                    <Link to="/platform/dashboard">
                      Go to Dashboard
                      <ExternalLink className="ml-2 h-4 w-4" />
                    </Link>
                  </Button>
                </div>
              </div>
            </div>
          </main>
        </div>
      </div>
    </FloatingLayout>
  )
}
