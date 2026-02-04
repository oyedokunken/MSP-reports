import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useToast } from '@/components/ui/Toast'
import { useAuth } from '@/contexts/AuthContext'
import { api } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { LogoUpload } from '@/components/ui/LogoUpload'
import { Mail, BadgeCheck, Shield } from 'lucide-react'


interface UserProfileData {
    user_id: number;
    name: string;
    email: string;
    profile_picture_url: string | null;
    role: {
        name: string;
    };
    last_login_at: string;
}

export default function Profile() {
    const { user, refreshUser } = useAuth()
    const toast = useToast()

    const [name, setName] = useState('')
    const [email, setEmail] = useState('')
    const [role, setRole] = useState('')
    const [profileImage, setProfileImage] = useState<string | null>(null)

    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)

    useEffect(() => {
        const fetchProfile = async () => {
            try {
                setLoading(true)
                const response = await api.get<{ data: UserProfileData }>('/auth/me');

                if (response?.data) {
                    setName(response.data.name || '')
                    setEmail(response.data.email || '')
                    setProfileImage(response.data.profile_picture_url || null)
                    setRole(response.data.role?.name || 'User')
                }
            } catch (error) {
                console.error('Failed to fetch profile', error)
                if (user) {
                    setName(user.name)
                    setEmail(user.email)
                    setProfileImage(user.profile_picture_url || null)
                    setRole(user.role?.name || 'User')
                }
            } finally {
                setLoading(false)
            }
        }
        fetchProfile()
    }, [user])

    const handleSave = async () => {
        if (!user?.user_id) return;

        try {
            setSaving(true)

            const response = await api.patch<{ status: string, data: UserProfileData }>(`/users/${user.user_id}`, {
                name,
            })

            if (response.status === 'success') {
                toast.success('Profile Updated', 'Your profile has been updated successfully.')
                refreshUser(); // Update global context
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Could not update profile.'
            toast.error('Update Failed', message)
        } finally {
            setSaving(false)
        }
    }

    const handleImageUpdate = async (url: string | null) => {
        setProfileImage(url);
        await refreshUser();
    }

    if (loading) {
        return (
            <div className="flex h-[50vh] items-center justify-center text-muted-foreground">
                Loading Profile...
            </div>
        )
    }

    return (
        <div className="space-y-8">

                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">My Profile</h1>
                        <p className="text-muted-foreground mt-1">Manage your account settings and preferences.</p>
                    </div>
                    {/* <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary/10 text-primary border border-primary/20 w-fit">
                        <Shield className="w-4 h-4" />
                        <span className="text-sm font-medium">{role}</span>
                    </div> */}
                </div>

                <div className="bg-card border rounded-xl shadow-sm p-6 md:p-10">
                    <div className="flex flex-col md:flex-row gap-10">

                        <div className="md:w-72 flex-shrink-0 flex flex-col items-center md:items-start space-y-6">
                            <LogoUpload
                                type="profile"
                                currentLogoUrl={profileImage}
                                onUploadSuccess={handleImageUpdate}
                                onDeleteSuccess={() => handleImageUpdate(null)}
                                uploadUrl={user?.user_id ? `/users/${user.user_id}/profile-picture` : undefined}
                                deleteUrl={user?.user_id ? `/users/${user.user_id}/profile-picture` : undefined}
                                fileKey="profile_picture"
                            />

                            <div className="w-full space-y-4">
                                <div className="p-4 rounded-lg bg-muted/30 border space-y-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-muted-foreground">Role</span>
                                        <span className="inline-flex items-center px-2 py-0.5 rounded-full bg-primary/10 text-primary text-xs font-medium border border-primary/20">
                                            {role}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-muted-foreground">Status</span>
                                        <span className="text-green-600 text-sm font-medium flex items-center gap-1">
                                            <span className="h-1.5 w-1.5 rounded-full bg-current" />
                                            Active
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-muted-foreground">Last Login</span>
                                        <span className="text-sm">{new Date().toLocaleDateString()}</span>
                                    </div>
                                </div>

                                <div className="pt-2">
                                    <Button variant="outline" className="w-full justify-start text-muted-foreground hover:text-foreground" asChild>
                                        <Link to="/admin/security">
                                            <Shield className="w-4 h-4 mr-2" />
                                            Security Settings
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div className="hidden md:block w-px bg-border" />

                        <div className="flex-1 max-w-2xl">
                            <div className="mb-6">
                                <h2 className="text-lg font-semibold">Personal Information</h2>
                                <p className="text-sm text-muted-foreground">Update your photo and personal details here.</p>
                            </div>

                            <div className="space-y-6">
                                <div className="grid gap-2">
                                    <label className="text-sm font-medium">Display Name</label>
                                    <input
                                        type="text"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        placeholder="Your full name"
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <label className="text-sm font-medium">Email Address</label>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <input
                                            type="email"
                                            value={email}
                                            disabled
                                            className="pl-9 flex h-10 w-full rounded-md border border-input bg-muted px-3 py-2 text-sm text-foreground/70 cursor-not-allowed"
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground flex items-center gap-1">
                                        <BadgeCheck className="w-3 h-3" /> Verified by administrator
                                    </p>
                                </div>

                                <div className="pt-6">
                                    <Button onClick={handleSave} disabled={saving} className="min-w-[120px]">
                                        {saving ? (
                                            <>
                                                <span className="animate-spin mr-2">⏳</span> Saving...
                                            </>
                                        ) : (
                                            "Save Changes"
                                        )}
                                    </Button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
    )
}
