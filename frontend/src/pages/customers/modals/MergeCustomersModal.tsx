import { useState, useEffect, useRef, useCallback } from "react";
import { Button } from "@/components/ui/button";
import { logger } from '@/lib/logger'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Spinner } from "@/components/ui/spinner";
import { CheckCircle2, GitMerge, AlertTriangle, Search } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { api } from "@/lib/api";
import type { Customer } from "../types";

interface MergeCustomersModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer: Customer | null;
    allCustomers: Customer[];
    onSuccess?: () => void;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export function MergeCustomersModal({
    open,
    onOpenChange,
    customer,
    allCustomers,
    onSuccess,
}: MergeCustomersModalProps) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState("");
    const [success, setSuccess] = useState(false);
    const [searchTerm, setSearchTerm] = useState("");
    const [selectedCustomerId, setSelectedCustomerId] = useState<number | null>(
        null
    );
    const [searchResults, setSearchResults] = useState<Customer[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [currentPage, setCurrentPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const observerTarget = useRef<HTMLDivElement>(null);

    // Load initial customers (no search)
    const loadInitialCustomers = useCallback(async () => {
        setIsSearching(true);
        try {
            const params = new URLSearchParams({
                page: '1',
                per_page: '20'
            });
            const response = await api.get(`/customers?${params.toString()}`);
            const data = response as { data?: Customer[]; pagination?: Pagination } | Customer[];
            const customers = Array.isArray(data) ? data : (data.data || []);
            const pagination = Array.isArray(data) ? { current_page: 1, last_page: 1 } : (data.pagination || { current_page: 1, last_page: 1 });

            // Map API response
            const mapped = customers.map((c) => ({
                ...c,
                name: c.canonical_name || c.name,
                integration_count: c.integrations?.length || 0
            }));

            // Filter out current customer
            const filtered = mapped.filter(
                (c: Customer) => c.customer_id !== customer?.customer_id
            );

            setSearchResults(filtered);

            // Check pagination
            const currentPageNum = (pagination as Pagination).current_page || 1;
            const lastPage = (pagination as Pagination).last_page || 1;
            setHasMore(currentPageNum < lastPage);
            setCurrentPage(currentPageNum);
        } catch (err) {
            logger.error('Failed to load customers:', err);
            setSearchResults([]);
            setHasMore(false);
        } finally {
            setIsSearching(false);
        }
    }, [customer]);

    useEffect(() => {
        if (open) {
            setSearchTerm("");
            setSelectedCustomerId(null);
            setError("");
            setSuccess(false);
            setSearchResults([]);
            setCurrentPage(1);
            setHasMore(true);
            // Load initial customers when modal opens
            loadInitialCustomers();
        }
    }, [open, loadInitialCustomers]);

    // Load more customers (with or without search)
    const loadMoreCustomers = useCallback(async (page: number, append: boolean = false) => {

        if (append) {
            setIsLoadingMore(true);
        } else {
            setIsSearching(true);
        }

        try {
            const params = new URLSearchParams({
                page: page.toString(),
                per_page: '20' // Load 20 at a time
            });

            // Add search if exists
            if (searchTerm.trim()) {
                params.append('search', searchTerm);
            }
            const response = await api.get(`/customers?${params.toString()}`);
            const data = response as { data?: Customer[]; pagination?: Pagination } | Customer[];
            const customers = Array.isArray(data) ? data : (data.data || []);
            const pagination = Array.isArray(data) ? { current_page: 1, last_page: 1 } : (data.pagination || { current_page: 1, last_page: 1 });

            // Map API response to Customer type (API returns canonical_name, we need name)
            const mapped = customers.map((c) => ({
                ...c,
                name: c.canonical_name || c.name,
                integration_count: c.integrations?.length || 0
            }));

            // Filter out the current customer
            const filtered = mapped.filter(
                (c: Customer) => c.customer_id !== customer?.customer_id
            );

            if (append) {
                setSearchResults(prev => [...prev, ...filtered]);
            } else {
                setSearchResults(filtered);
            }

            // Check if there are more pages
            const currentPageNum = pagination.current_page || page;
            const lastPage = pagination.last_page || 1;
            setHasMore(currentPageNum < lastPage);
            setCurrentPage(currentPageNum);
        } catch (err) {
            logger.error('Failed to search customers:', err);
            if (!append) {
                setSearchResults([]);
            }
            setHasMore(false);
        } finally {
            setIsSearching(false);
            setIsLoadingMore(false);
        }
    }, [searchTerm, customer?.customer_id]);

    // Search when search term changes
    useEffect(() => {
        if (!open) return;

        setCurrentPage(1);
        setHasMore(true);

        // Debounce search
        const timeoutId = setTimeout(() => loadMoreCustomers(1, false), 300);
        return () => clearTimeout(timeoutId);
    }, [searchTerm, open, loadMoreCustomers]);

    // Infinite scroll observer
    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore && !isLoadingMore && !isSearching) {
                    loadMoreCustomers(currentPage + 1, true);
                }
            },
            { threshold: 0.1 }
        );

        const currentTarget = observerTarget.current;
        if (currentTarget) {
            observer.observe(currentTarget);
        }

        return () => {
            if (currentTarget) {
                observer.unobserve(currentTarget);
            }
        };
    }, [hasMore, isLoadingMore, isSearching, currentPage, loadMoreCustomers]);

    // Always use searchResults (includes initial load and search)
    const filteredCustomers = searchResults;

    const selectedCustomer = allCustomers.find(
        (c) => c.customer_id === selectedCustomerId
    );

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedCustomerId) {
            setError("Please select a customer to merge with");
            return;
        }

        setError("");
        setIsSubmitting(true);

        try {
            await api.post(`/customers/${customer?.customer_id}/merge`, {
                merge_customer_id: selectedCustomerId,
            });

            setSuccess(true);
            setTimeout(() => {
                setSuccess(false);
                onOpenChange(false);
                onSuccess?.();
            }, 2000);
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string } }; message?: string };
            const errorMessage =
                error.response?.data?.message ||
                error.message ||
                "Failed to merge customers";
            setError(errorMessage);
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        if (!isSubmitting) {
            onOpenChange(false);
        }
    };

    if (success) {
        return (
            <Dialog open={open} onOpenChange={handleClose}>
                <DialogContent className="sm:max-w-md">
                    <div className="flex flex-col items-center justify-center py-8">
                        <div className="mb-4 rounded-full bg-green-500/10 p-3">
                            <CheckCircle2 className="h-12 w-12 text-green-500" />
                        </div>
                        <DialogTitle className="text-2xl text-green-600 mb-2">
                            Customers Merged!
                        </DialogTitle>
                        <DialogDescription className="text-center">
                            The customers have been merged successfully.
                        </DialogDescription>
                    </div>
                </DialogContent>
            </Dialog>
        );
    }

    if (!customer) return null;
    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <div className="flex items-center gap-2">
                        <GitMerge className="h-5 w-5 text-primary" />
                        <DialogTitle>Merge Customers</DialogTitle>
                    </div>
                    <DialogDescription>
                        Merge {customer.name} into another customer
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Warning */}
                    <div className="rounded-lg border border-orange-500/50 bg-orange-500/10 p-3">
                        <div className="flex items-start gap-2">
                            <AlertTriangle className="h-5 w-5 text-orange-600 flex-shrink-0 mt-0.5" />
                            <div>
                                <p className="text-sm text-orange-700 dark:text-orange-400 font-medium mb-1">
                                    ⚠️ Warning: This action cannot be undone!
                                </p>
                                <ul className="text-xs text-orange-700/80 dark:text-orange-400/80 space-y-1 list-disc list-inside">
                                    <li>
                                        All mappings from "{customer.name}" will
                                        be moved to the target customer
                                    </li>
                                    <li>"{customer.name}" will be deleted</li>
                                    <li>
                                        Report schedules will be transferred
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {/* Search */}
                    <div>
                        <Label
                            htmlFor="search"
                            className="text-sm font-medium mb-2 block"
                        >
                            Search for customer to merge into
                        </Label>
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="search"
                                type="text"
                                placeholder="Search customers..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                disabled={isSubmitting}
                                className="pl-10"
                            />
                        </div>
                    </div>

                    {/* Customer List */}
                    <div className="max-h-64 overflow-y-auto space-y-2 border border-border rounded-lg p-2">
                        {isSearching && currentPage === 1 ? (
                            <div className="flex items-center justify-center py-8">
                                <Spinner className="h-6 w-6" />
                                <span className="ml-2 text-sm text-muted-foreground">Searching...</span>
                            </div>
                        ) : filteredCustomers.length === 0 ? (
                            <p className="text-sm text-muted-foreground text-center py-4">
                                {searchTerm.trim() ? 'No customers found matching your search' : 'No customers found'}
                            </p>
                        ) : (
                            <>
                                <p className="text-xs text-muted-foreground px-2 py-1">
                                    Showing {filteredCustomers.length} result{filteredCustomers.length !== 1 ? 's' : ''}
                                    {hasMore && ' (scroll for more)'}
                                </p>
                                {filteredCustomers.map((c) => (
                                    <button
                                        key={c.customer_id}
                                        type="button"
                                        onClick={() =>
                                            setSelectedCustomerId(c.customer_id)
                                        }
                                        className={`w-full text-left p-3 rounded-lg border transition-colors ${selectedCustomerId === c.customer_id
                                            ? "border-primary bg-primary/10"
                                            : "border-border hover:bg-accent"
                                            }`}
                                    >
                                        <p className="font-medium">{c.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {c.integration_count} integration
                                            {c.integration_count !== 1 ? "s" : ""}
                                        </p>
                                    </button>
                                ))}

                                {/* Infinite scroll trigger */}
                                <div ref={observerTarget} className="py-2">
                                    {isLoadingMore && (
                                        <div className="flex items-center justify-center py-2">
                                            <Spinner className="h-4 w-4" />
                                            <span className="ml-2 text-xs text-muted-foreground">Loading more...</span>
                                        </div>
                                    )}
                                </div>
                            </>
                        )}
                    </div>

                    {selectedCustomer && (
                        <div className="rounded-lg border border-border bg-accent/50 p-3">
                            <p className="text-sm font-medium mb-1">
                                Merging into:
                            </p>
                            <p className="font-semibold">
                                {selectedCustomer.name}
                            </p>
                        </div>
                    )}

                    {error && (
                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-3">
                            <p className="text-sm text-destructive font-medium">
                                {error}
                            </p>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={isSubmitting || !selectedCustomerId}
                        >
                            {isSubmitting ? (
                                <>
                                    <Spinner className="mr-2 h-4 w-4" />
                                    Merging...
                                </>
                            ) : (
                                <>
                                    <GitMerge className="mr-2 h-4 w-4" />
                                    Merge Customers
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
