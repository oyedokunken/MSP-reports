import { useState, useRef, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { X, Send, Bot, Sparkles } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { findAnswer } from './knowledgeBase'
import { useAuth } from '@/contexts/AuthContext'

// Convert markdown-style links [text](url) to clickable elements
// Convert markdown-style links [text](url) to clickable elements
function parseMessageContent(content: string): string {
  // Convert markdown links to clickable spans with data attributes
  // Pattern: [link text](/path/to/page)
  return content
    .replace(/\[([^\]]+)\]\((\/[^)]+)\)/g, '<a href="$2" class="chatbot-link text-primary underline hover:text-primary/80 cursor-pointer font-medium" data-internal-link="$2">$1</a>')
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g, '<br/>')
}

// ... inside component ...
// dangerouslySetInnerHTML={{ 
//   __html: parseMessageContent(message.content)
// }}

interface Message {
  id: string
  role: 'user' | 'assistant'
  content: string
  timestamp: Date
}

export function SupportChatbot() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [isOpen, setIsOpen] = useState(false)
  const [hasShownWelcome, setHasShownWelcome] = useState(false)
  const [showPulse, setShowPulse] = useState(true)

  const firstName = user?.name?.split(' ')[0] || 'there'

  const getWelcomeMessage = useCallback(() => ({
    id: '1',
    role: 'assistant' as const,
    content: `Hi ${firstName}! 👋 I'm your **AI Assistant** for the MSP Reporting Platform.\n\nI can help you with:\n\n• [Integrations](/platform/integrations) - Connect Autotask, Datto, ITGlue & more\n• [Customers](/platform/customers/master) - Mapping & management\n• [Reports](/platform/reports) - Schedules & delivery\n• [Admin](/admin/users) - Users, API keys, audit logs\n• [Monitoring](/admin/monitoring) - System health & settings\n\nJust ask me anything or pick a quick question below!`,
    timestamp: new Date(),
  }), [firstName])

  const [messages, setMessages] = useState<Message[]>([getWelcomeMessage()])
  const [input, setInput] = useState('')
  const [isTyping, setIsTyping] = useState(false)
  const messagesEndRef = useRef<HTMLDivElement>(null)

  // Update welcome message when user changes
  useEffect(() => {
    if (user && !hasShownWelcome) {
      setMessages([getWelcomeMessage()])
      setHasShownWelcome(true)
    }
  }, [user, hasShownWelcome, getWelcomeMessage])

  // Hide pulse after first open
  useEffect(() => {
    if (isOpen) {
      setShowPulse(false)
    }
  }, [isOpen])

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }

  useEffect(() => {
    scrollToBottom()
  }, [messages])

  const handleSend = () => {
    if (!input.trim()) return

    // Add user message
    const userMessage: Message = {
      id: Date.now().toString(),
      role: 'user',
      content: input,
      timestamp: new Date(),
    }

    setMessages((prev) => [...prev, userMessage])
    setInput('')
    setIsTyping(true)

    // Simulate typing delay
    setTimeout(() => {
      const answer = findAnswer(input)
      const assistantMessage: Message = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content: answer,
        timestamp: new Date(),
      }
      setMessages((prev) => [...prev, assistantMessage])
      setIsTyping(false)
    }, 500)
  }

  const handleQuickQuestion = (question: string) => {
    setInput(question)
    setTimeout(() => handleSend(), 100)
  }

  const quickQuestions = [
    'How do I add an integration?',
    'How to map customers?',
    'How to configure reports?',
    'How to create API keys?',
    'View audit logs',
    'Where do I find settings?',
  ]

  if (!isOpen) {
    return (
      <div className="fixed bottom-6 right-6 z-50" data-tour="chatbot-button">
        {/* Pulse animation ring */}
        {showPulse && (
          <div className="absolute inset-0 rounded-full bg-primary/30 animate-ping" />
        )}
        <Button
          onClick={() => setIsOpen(true)}
          className="relative h-16 w-16 rounded-full shadow-xl hover:shadow-2xl transition-all hover:scale-110 bg-gradient-to-br from-primary to-primary/80"
          size="icon"
        >
          <Bot className="h-7 w-7" />
          {showPulse && (
            <span className="absolute -top-1 -right-1 flex h-5 w-5">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-5 w-5 bg-green-500 items-center justify-center">
                <Sparkles className="h-3 w-3 text-white" />
              </span>
            </span>
          )}
        </Button>
      </div>
    )
  }

  return (
    <Card className="fixed bottom-6 right-6 w-[400px] max-w-[calc(100vw-3rem)] h-[650px] max-h-[calc(100vh-3rem)] shadow-2xl z-50 flex flex-col rounded-2xl overflow-hidden border-2 border-primary/20 animate-in slide-in-from-bottom-4 duration-300 !gap-0 !p-0">
      <div className="flex flex-row items-center justify-between px-5 py-3 border-b border-primary/20 bg-gradient-to-r from-primary/15 via-primary/10 to-primary/5 backdrop-blur-sm">
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center shadow-md">
            <Bot className="h-5 w-5 text-white" />
          </div>
          <div className="leading-tight">
            <h3 className="text-lg font-bold text-foreground m-0 leading-tight">AI Assistant</h3>
            <p className="text-xs text-muted-foreground m-0 leading-tight">Here to help</p>
          </div>
        </div>
        <Button
          variant="ghost"
          size="icon"
          onClick={() => setIsOpen(false)}
          className="h-9 w-9 rounded-full hover:bg-destructive/10 hover:text-destructive transition-colors"
        >
          <X className="h-5 w-5" />
        </Button>
      </div>

      <CardContent className="flex-1 flex flex-col p-0 overflow-hidden bg-gradient-to-b from-background to-muted/20">
        {/* Messages */}
        <div className="flex-1 overflow-y-auto p-5 space-y-3">
          {messages.map((message) => (
            <div
              key={message.id}
              className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
            >
              <div
                className={`max-w-[85%] rounded-2xl px-4 py-3 shadow-md transition-all hover:shadow-lg ${message.role === 'user'
                  ? 'bg-gradient-to-br from-primary to-primary/90 text-primary-foreground'
                  : 'bg-card text-foreground border border-border/50 backdrop-blur-sm'
                  }`}
              >
                <div
                  className="text-sm whitespace-pre-wrap prose prose-sm max-w-none dark:prose-invert"
                  onClick={(e) => {
                    const target = e.target as HTMLElement
                    if (target.tagName === 'A' && target.dataset.internalLink) {
                      e.preventDefault()
                      navigate(target.dataset.internalLink)
                      setIsOpen(false)
                    }
                  }}
                  dangerouslySetInnerHTML={{
                    __html: parseMessageContent(message.content)
                  }}
                />
                <span className="text-xs opacity-70 mt-1 block">
                  {message.timestamp.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </span>
              </div>
            </div>
          ))}

          {isTyping && (
            <div className="flex justify-start">
              <div className="bg-card border border-border/50 rounded-2xl px-5 py-3 shadow-md">
                <div className="flex gap-1.5">
                  <div className="w-2.5 h-2.5 bg-primary rounded-full animate-bounce" />
                  <div className="w-2.5 h-2.5 bg-primary rounded-full animate-bounce" style={{ animationDelay: '0.1s' }} />
                  <div className="w-2.5 h-2.5 bg-primary rounded-full animate-bounce" style={{ animationDelay: '0.2s' }} />
                </div>
              </div>
            </div>
          )}

          <div ref={messagesEndRef} />
        </div>

        {/* Quick Questions */}
        {messages.length === 1 && (
          <div className="px-5 pb-3 border-t border-border/50 pt-4 bg-muted/30">
            <p className="text-xs font-semibold text-muted-foreground mb-3">
              Quick Questions
            </p>
            <div className="flex flex-wrap gap-2">
              {quickQuestions.map((question, index) => (
                <button
                  key={index}
                  onClick={() => handleQuickQuestion(question)}
                  className="text-xs px-3.5 py-2 rounded-full bg-gradient-to-r from-primary/15 to-primary/10 hover:from-primary/25 hover:to-primary/20 text-primary font-medium transition-all hover:scale-105 hover:shadow-md border border-primary/20"
                >
                  {question}
                </button>
              ))}
            </div>
          </div>
        )}

        {/* Input */}
        <div className="p-4 border-t border-border/50 bg-background">
          <div className="flex gap-2">
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && handleSend()}
              placeholder="Type your question here..."
              className="flex-1 px-4 py-3 text-sm border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent bg-background transition-all"
            />
            <Button
              onClick={handleSend}
              size="icon"
              disabled={!input.trim()}
              className="h-11 w-11 rounded-xl bg-gradient-to-br from-primary to-primary/80 hover:from-primary hover:to-primary disabled:opacity-50 transition-all hover:scale-105"
            >
              <Send className="h-5 w-5" />
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
